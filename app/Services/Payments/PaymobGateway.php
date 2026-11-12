<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Contracts\RetrievesGatewayRefunds;
use App\Contracts\TestsGatewayConnection;
use App\Enums\GatewayMode;
use App\Enums\PaymentStatus;
use App\Exceptions\InvalidWebhookSignature;
use App\Exceptions\ManualRefundRequired;
use App\Exceptions\PaymentGatewayException;
use App\Models\Payment;
use App\Models\PaymentLink;
use App\Models\Refund;
use App\Services\Payments\Concerns\CallsGatewayApi;
use App\Services\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Throwable;

/**
 * Paymob (Egypt) through the Intention API and Unified Checkout, over Laravel's HTTP client. Amounts are piasters
 * (amount_cents), currency EGP.
 *
 * - Checkout: POST {base}/v1/intention/ with `Authorization: Token <secret key>` (Paymob docs → Intention APIs →
 *   Create Intention); the payer is sent to {base}/unifiedcheckout/?publicKey=…&clientSecret=… (Checkout
 *   experiences → Unified Checkout redirection). `special_reference` is our payment ulid (comes back as
 *   merchant_order_id); `intention_order_id` (the Paymob order id) is stored as gateway_payment_id.
 * - Verification: an auth token from POST {base}/api/auth/tokens (API key), then
 *   POST {base}/api/ecommerce/orders/transaction_inquiry {order_id} — the callback body is never trusted.
 * - Webhooks (Transaction Processed callback, POST JSON, `?hmac=` query parameter): HMAC-SHA512 with the account's
 *   HMAC secret over the values of the 20 documented fields concatenated in the documented (lexicographic) order,
 *   booleans as "true"/"false" (Paymob docs → Webhook callbacks and HMAC → HMAC Transaction Callback).
 * - Refunds: POST {base}/api/acceptance/void_refund/refund {transaction_id, amount_cents} with the secret key
 *   (Paymob docs → Refund Transaction); cash (kiosk) payments need a manual refund.
 * - Connection test: the auth-token call (authenticated, no side effects).
 *
 * Credentials (Settings → Payments): paymob_secret_key, paymob_public_key, paymob_api_key, paymob_hmac_secret,
 * paymob_integration_ids, paymob_mode.
 */
class PaymobGateway implements PaymentGateway, RetrievesGatewayRefunds, TestsGatewayConnection
{
    use CallsGatewayApi;

    /**
     * Transaction Processed callback HMAC fields, in the documented order. "id" and "order.id" are read from obj.
     *
     * @var list<string>
     */
    public const HMAC_FIELDS = [
        'amount_cents', 'created_at', 'currency', 'error_occured', 'has_parent_transaction', 'id', 'integration_id',
        'is_3d_secure', 'is_auth', 'is_capture', 'is_refunded', 'is_standalone_payment', 'is_voided', 'order.id',
        'owner', 'pending', 'source_data.pan', 'source_data.sub_type', 'source_data.type', 'success',
    ];

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(private Settings $settings, private string $key = 'paymob', private array $config = []) {}

    public function key(): string
    {
        return $this->key;
    }

    public function isConfigured(): bool
    {
        return collect((array) ($this->config['credentials'] ?? []))->every(fn (string $credential): bool => $this->setting($credential) !== null);
    }

    public function mode(): GatewayMode
    {
        $mode = $this->setting('paymob_mode');

        if ($mode !== null && GatewayMode::tryFrom($mode) !== null) {
            return GatewayMode::from($mode);
        }

        return str_contains((string) $this->setting('paymob_secret_key'), '_live_') ? GatewayMode::Live : GatewayMode::Test;
    }

    public function supportsCurrency(string $currency): bool
    {
        return $this->supportsListedCurrency($currency, (array) ($this->config['currencies'] ?? []));
    }

    public function createCheckout(PaymentLink $link, Payment $payment): CheckoutSession
    {
        $payment->loadMissing('customer');
        $name = Str::limit($link->description ?: __('Payment :reference', ['reference' => $link->reference]), 250, '');
        [$firstName, $lastName] = array_pad(explode(' ', trim((string) ($payment->customer->contact_name ?: $payment->customer->company_name)), 2), 2, '');

        $response = $this->call(fn () => $this->http()
            ->withHeaders(['Authorization' => 'Token '.$this->requireSetting('paymob_secret_key')])
            ->post($this->url('/v1/intention/'), [
                'amount' => $payment->amount_minor,
                'currency' => mb_strtoupper($payment->currency),
                'payment_methods' => $this->integrationIds(),
                'items' => [['name' => $name, 'amount' => $payment->amount_minor, 'description' => (string) $link->reference, 'quantity' => 1]],
                // Paymob requires billing fields; "NA" is the documented placeholder for unknown values.
                'billing_data' => [
                    'first_name' => $firstName !== '' ? Str::limit($firstName, 50, '') : 'NA',
                    'last_name' => $lastName !== '' ? Str::limit($lastName, 50, '') : 'NA',
                    'email' => $payment->customer->email ?: 'NA',
                    'phone_number' => $payment->customer->phone ?: 'NA',
                    'country' => $payment->customer->country ?: 'NA',
                ],
                'special_reference' => $payment->ulid,
                'notification_url' => route('webhooks.payments', $this->key),
                'redirection_url' => route('pay.return', ['paymentLink' => $link, 'payment' => $payment]),
                'extras' => ['payment_ulid' => $payment->ulid, 'payment_reference' => (string) $payment->reference],
            ]), 'detail');

        $orderId = $response->json('intention_order_id');
        $clientSecret = $response->json('client_secret');

        if (! is_scalar($orderId) || (string) $orderId === '' || ! is_string($clientSecret) || $clientSecret === '') {
            throw new PaymentGatewayException('Unexpected intention response');
        }

        $url = $this->url('/unifiedcheckout/').'?'.http_build_query(['publicKey' => $this->requireSetting('paymob_public_key'), 'clientSecret' => $clientSecret]);

        return new CheckoutSession((string) $orderId, $url, metadata: array_filter([
            'paymob_order_id' => (string) $orderId,
            'paymob_intention_id' => is_string($response->json('id')) ? $response->json('id') : null,
        ]));
    }

    public function verifyPayment(Payment $payment): GatewayVerification
    {
        if (! is_string($payment->gateway_payment_id) || ! ctype_digit($payment->gateway_payment_id)) {
            throw new PaymentGatewayException('Payment has no Paymob order');
        }

        $token = $this->authToken();

        try {
            $transaction = $this->call(fn () => $this->http()->withToken($token)
                ->post($this->url('/api/ecommerce/orders/transaction_inquiry'), ['order_id' => (int) $payment->gateway_payment_id]))->json();
        } catch (PaymentGatewayException $exception) {
            // No transaction on the order yet: the payer has not completed the checkout.
            if (str_starts_with($exception->getMessage(), 'HTTP 404')) {
                return new GatewayVerification(PaymentStatus::Pending, $payment->amount_minor, $payment->currency);
            }

            throw $exception;
        }

        if (! is_array($transaction) || ! isset($transaction['id'])) {
            throw new PaymentGatewayException('Unexpected transaction response');
        }

        if ((string) Arr::get($transaction, 'order.id', $payment->gateway_payment_id) !== $payment->gateway_payment_id) {
            throw new PaymentGatewayException('Transaction belongs to another order');
        }

        $success = ($transaction['success'] ?? false) === true;
        $pending = ($transaction['pending'] ?? false) === true;
        $authorisedOnly = ($transaction['is_auth'] ?? false) === true && ($transaction['is_capture'] ?? false) !== true;

        $status = match (true) {
            $pending || $authorisedOnly => PaymentStatus::Pending,
            ($transaction['is_voided'] ?? false) === true => PaymentStatus::Cancelled,
            $success => PaymentStatus::Paid,
            default => PaymentStatus::Failed,
        };

        return new GatewayVerification(
            status: $status,
            amountMinor: (int) ($transaction['amount_cents'] ?? 0),
            currency: mb_strtoupper((string) ($transaction['currency'] ?? '')),
            paidAt: $status === PaymentStatus::Paid ? $this->timestamp($transaction['created_at'] ?? null) : null,
            metadata: array_filter([
                'paymob_order_id' => $payment->gateway_payment_id,
                'transaction_id' => (string) $transaction['id'],
                'payment_method_type' => $this->methodType(Arr::get($transaction, 'source_data.type')),
                'paymob_source_type' => is_string(Arr::get($transaction, 'source_data.type')) ? Arr::get($transaction, 'source_data.type') : null,
                'livemode' => ($transaction['is_live'] ?? null) === null ? null : (bool) $transaction['is_live'],
            ], fn ($value): bool => $value !== null),
            failureReason: $status === PaymentStatus::Failed
                ? Str::limit((string) (Arr::get($transaction, 'data.txn_response_code') ?: Arr::get($transaction, 'data.message') ?: 'declined'), 100, '')
                : ($status === PaymentStatus::Cancelled ? 'voided' : null),
        );
    }

    public function parseWebhook(Request $request): GatewayWebhookEvent
    {
        $secret = $this->setting('paymob_hmac_secret') ?? throw new InvalidWebhookSignature('HMAC secret not configured');
        $received = (string) $request->query('hmac', '');
        $body = json_decode($request->getContent(), true);

        if ($received === '' || ! is_array($body) || ! is_array($body['obj'] ?? null)) {
            throw new InvalidWebhookSignature('Missing signature');
        }

        if (($body['type'] ?? null) !== 'TRANSACTION') {
            // Only transaction callbacks carry the documented HMAC fields we verify.
            throw new InvalidWebhookSignature('Unsupported callback type');
        }

        $object = $body['obj'];

        if (! hash_equals(self::transactionHmac($object, $secret), mb_strtolower($received))) {
            throw new InvalidWebhookSignature('Signature mismatch');
        }

        if (! isset($object['id']) || ! is_scalar($object['id'])) {
            throw new InvalidWebhookSignature('Malformed event');
        }

        $transactionId = (string) $object['id'];
        $isRefund = ($object['is_refund'] ?? false) === true;
        $orderId = Arr::get($object, 'order.id');
        $merchantOrderId = Arr::get($object, 'order.merchant_order_id');

        return new GatewayWebhookEvent(
            eventId: 'TRANSACTION-'.$transactionId.'-'.(($object['success'] ?? false) === true ? 'success' : (($object['pending'] ?? false) === true ? 'pending' : 'failed')),
            type: $isRefund ? 'transaction.refund' : 'transaction.processed',
            gatewayPaymentId: ! $isRefund && is_scalar($orderId) ? (string) $orderId : null,
            paymentUlid: ! $isRefund && is_string($merchantOrderId) ? $merchantOrderId : null,
            payload: [
                'type' => 'TRANSACTION',
                'obj' => array_filter([
                    ...Arr::only($object, ['id', 'success', 'pending', 'amount_cents', 'currency', 'is_refund', 'is_refunded', 'is_voided', 'created_at']),
                    'order_id' => $orderId,
                    'merchant_order_id' => $merchantOrderId,
                    'parent_transaction' => $object['parent_transaction'] ?? null,
                    'source_type' => Arr::get($object, 'source_data.type'),
                ], fn ($value): bool => $value !== null),
            ],
            concernsPayment: ! $isRefund,
            gatewayRefundId: $isRefund ? $transactionId : null,
        );
    }

    public function refund(Payment $payment, int $amountMinor): GatewayRefund
    {
        $transactionId = $payment->metadata['transaction_id'] ?? null;

        // Cash collected at Aman/Masary kiosks cannot be returned through the API.
        if (in_array($payment->metadata['paymob_source_type'] ?? null, ['cash_present', 'aggregator', 'kiosk'], true)) {
            throw new ManualRefundRequired('Paymob cannot refund cash payments through the API');
        }

        if (! is_string($transactionId) || $transactionId === '') {
            throw new PaymentGatewayException('Payment has no Paymob transaction');
        }

        $response = $this->call(fn () => $this->http()
            ->withHeaders(['Authorization' => 'Token '.$this->requireSetting('paymob_secret_key')])
            ->post($this->url('/api/acceptance/void_refund/refund'), ['transaction_id' => (int) $transactionId, 'amount_cents' => $amountMinor]), 'detail');

        return $this->refundResult($response->json(), $amountMinor);
    }

    public function refundStatus(Refund $refund): GatewayRefund
    {
        if (! is_string($refund->gateway_refund_id) || ! ctype_digit($refund->gateway_refund_id)) {
            throw new PaymentGatewayException('Refund has no Paymob transaction');
        }

        $token = $this->authToken();

        return $this->refundResult(
            $this->call(fn () => $this->http()->withToken($token)->get($this->url('/api/acceptance/transactions/'.$refund->gateway_refund_id)))->json(),
            $refund->amount_minor,
        );
    }

    public function testConnection(): void
    {
        $this->authToken();
    }

    /**
     * The documented Transaction Processed HMAC: SHA-512 HMAC of the concatenated field values.
     *
     * @param  array<string, mixed>  $transaction  the callback's `obj`
     */
    public static function transactionHmac(array $transaction, string $secret): string
    {
        $values = array_map(function (string $field) use ($transaction): string {
            $value = Arr::get($transaction, $field);

            return match (true) {
                is_bool($value) => $value ? 'true' : 'false',
                $value === null => '',
                is_scalar($value) => (string) $value,
                default => '',
            };
        }, self::HMAC_FIELDS);

        return hash_hmac('sha512', implode('', $values), $secret);
    }

    /**
     * @param  mixed  $transaction  decoded refund transaction
     */
    private function refundResult(mixed $transaction, int $amountMinor): GatewayRefund
    {
        if (! is_array($transaction) || ! isset($transaction['id']) || ! is_scalar($transaction['id'])) {
            throw new PaymentGatewayException('Unexpected refund response');
        }

        $status = match (true) {
            ($transaction['pending'] ?? false) === true => GatewayRefund::PENDING,
            ($transaction['success'] ?? false) === true => GatewayRefund::SUCCEEDED,
            default => GatewayRefund::FAILED,
        };

        return new GatewayRefund(
            (string) $transaction['id'],
            $status,
            (int) ($transaction['amount_cents'] ?? $amountMinor),
            $status === GatewayRefund::FAILED ? Str::limit((string) (Arr::get($transaction, 'data.message') ?: 'refund_declined'), 100, '') : null,
        );
    }

    /**
     * @throws PaymentGatewayException
     */
    private function authToken(): string
    {
        $token = $this->call(fn () => $this->http()->post($this->url('/api/auth/tokens'), ['api_key' => $this->requireSetting('paymob_api_key')]))->json('token');

        if (! is_string($token) || $token === '') {
            throw new PaymentGatewayException('Authentication failed');
        }

        return $token;
    }

    /**
     * @return list<int>
     */
    private function integrationIds(): array
    {
        $ids = array_values(array_filter(array_map('trim', explode(',', (string) $this->setting('paymob_integration_ids'))), 'ctype_digit'));

        return $ids === [] ? throw new PaymentGatewayException('Requires credentials') : array_map('intval', $ids);
    }

    private function methodType(mixed $sourceType): ?string
    {
        return match ($sourceType) {
            'card' => 'card',
            'wallet' => 'wallet',
            null => null,
            default => is_string($sourceType) ? Str::snake($sourceType) : null,
        };
    }

    private function timestamp(mixed $value): ?CarbonImmutable
    {
        try {
            return is_string($value) && $value !== '' ? CarbonImmutable::parse($value) : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function url(string $path): string
    {
        return rtrim((string) ($this->config['base_url'] ?? 'https://accept.paymob.com'), '/').$path;
    }
}
