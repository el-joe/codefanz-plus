<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Contracts\RetrievesGatewayRefunds;
use App\Contracts\TestsGatewayConnection;
use App\Enums\GatewayMode;
use App\Enums\PaymentStatus;
use App\Exceptions\InvalidWebhookSignature;
use App\Exceptions\PaymentGatewayException;
use App\Models\Payment;
use App\Models\PaymentLink;
use App\Models\Refund;
use App\Services\Payments\Concerns\CallsGatewayApi;
use App\Services\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Throwable;

/**
 * PayTabs PT2 Hosted Payment Page over Laravel's HTTP client. The region's API host is chosen in Settings
 * (EGY, SAU, ARE or GLOBAL); amounts are decimals with two places.
 *
 * - Checkout: POST {region}/payment/request with `authorization: <server key>`, tran_type "sale", tran_class
 *   "ecom", cart_id = our payment ulid (PayTabs docs → Hosted Payment Page → Step 3 initiate the payment);
 *   the response's tran_ref is stored as gateway_payment_id and the payer goes to redirect_url.
 * - Verification: POST {region}/payment/query {profile_id, tran_ref}; payment_result.response_status "A" is the
 *   only approved status (HPP Step 5 → handle the payment response / transaction query).
 * - Callbacks (server-to-server "callback"/IPN): the `signature` header is HMAC-SHA256 of the raw request body
 *   keyed with the profile server key (PayTabs support → "How to validate the callback/IPN signature").
 * - Refunds: POST {region}/payment/request with tran_type "refund" and the original tran_ref (follow-up
 *   transactions); refund callbacks are confirmed with the query API.
 * - Connection test: a query for a non-existent tran_ref — authentication errors fail the test, a "not found"
 *   answer proves the server key and profile id are accepted.
 *
 * Credentials (Settings → Payments): paytabs_profile_id, paytabs_server_key, paytabs_region, paytabs_mode.
 */
class PayTabsGateway implements PaymentGateway, RetrievesGatewayRefunds, TestsGatewayConnection
{
    use CallsGatewayApi;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(private Settings $settings, private string $key = 'paytabs', private array $config = []) {}

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
        // PayTabs test and live profiles share the API hosts; the mode is what the merchant declared.
        return GatewayMode::tryFrom((string) $this->setting('paytabs_mode')) ?? GatewayMode::Test;
    }

    public function region(): string
    {
        $region = mb_strtoupper((string) $this->setting('paytabs_region'));

        return array_key_exists($region, (array) ($this->config['regions'] ?? [])) ? $region : (string) ($this->config['default_region'] ?? 'EGY');
    }

    public function supportsCurrency(string $currency): bool
    {
        return $this->supportsListedCurrency($currency, (array) ($this->config['regions'][$this->region()]['currencies'] ?? []));
    }

    public function createCheckout(PaymentLink $link, Payment $payment): CheckoutSession
    {
        $payment->loadMissing('customer');

        $response = $this->post('/payment/request', [
            'profile_id' => $this->profileId(),
            'tran_type' => 'sale',
            'tran_class' => 'ecom',
            'cart_id' => $payment->ulid,
            'cart_currency' => mb_strtoupper($payment->currency),
            'cart_amount' => (float) self::decimal($payment->amount_minor),
            'cart_description' => Str::limit($link->description ?: __('Payment :reference', ['reference' => $link->reference]), 120, ''),
            'paypage_lang' => app()->getLocale() === 'ar' ? 'ar' : 'en',
            'callback' => route('webhooks.payments', $this->key),
            'return' => route('pay.return', ['paymentLink' => $link, 'payment' => $payment]),
            'hide_shipping' => true,
            'customer_details' => array_filter([
                'name' => $payment->customer->contact_name ?: $payment->customer->company_name,
                'email' => $payment->customer->email,
                'phone' => $payment->customer->phone,
                'country' => $payment->customer->country,
            ]),
        ]);

        $tranRef = $response->json('tran_ref');
        $url = $response->json('redirect_url');

        if (! is_string($tranRef) || $tranRef === '' || ! is_string($url) || ! str_starts_with($url, 'https://')) {
            throw new PaymentGatewayException('Unexpected payment request response');
        }

        return new CheckoutSession($tranRef, $url, metadata: ['tran_ref' => $tranRef, 'paytabs_region' => $this->region()]);
    }

    public function verifyPayment(Payment $payment): GatewayVerification
    {
        if (! is_string($payment->gateway_payment_id) || $payment->gateway_payment_id === '') {
            throw new PaymentGatewayException('Payment has no PayTabs transaction');
        }

        $transaction = $this->query($payment->gateway_payment_id);

        if (($transaction['cart_id'] ?? $payment->ulid) !== $payment->ulid) {
            throw new PaymentGatewayException('Transaction belongs to another cart');
        }

        $result = (string) Arr::get($transaction, 'payment_result.response_status', '');

        // A approved, H on hold, P pending, V voided, E error, D declined, X expired.
        $status = match ($result) {
            'A' => PaymentStatus::Paid,
            'V', 'X' => PaymentStatus::Cancelled,
            'D', 'E' => PaymentStatus::Failed,
            default => PaymentStatus::Pending,
        };

        return new GatewayVerification(
            status: $status,
            amountMinor: self::minorFromDecimal($transaction['tran_total'] ?? $transaction['cart_amount'] ?? 0),
            currency: mb_strtoupper((string) ($transaction['tran_currency'] ?? $transaction['cart_currency'] ?? '')),
            paidAt: $status === PaymentStatus::Paid ? $this->timestamp(Arr::get($transaction, 'payment_result.transaction_time')) : null,
            metadata: array_filter([
                'tran_ref' => $payment->gateway_payment_id,
                'response_status' => $result !== '' ? $result : null,
                'response_code' => is_scalar(Arr::get($transaction, 'payment_result.response_code')) ? (string) Arr::get($transaction, 'payment_result.response_code') : null,
                'payment_method_type' => is_string(Arr::get($transaction, 'payment_info.payment_method')) ? 'card' : null,
                'card_scheme' => is_string(Arr::get($transaction, 'payment_info.card_scheme')) ? Arr::get($transaction, 'payment_info.card_scheme') : null,
            ], fn ($value): bool => $value !== null),
            failureReason: in_array($status, [PaymentStatus::Failed, PaymentStatus::Cancelled], true)
                ? Str::limit((string) (Arr::get($transaction, 'payment_result.response_message') ?: 'status_'.$result), 100, '')
                : null,
        );
    }

    public function parseWebhook(Request $request): GatewayWebhookEvent
    {
        $serverKey = $this->setting('paytabs_server_key') ?? throw new InvalidWebhookSignature('Server key not configured');
        $signature = mb_strtolower((string) $request->header('signature', ''));
        $body = $request->getContent();

        if ($signature === '') {
            throw new InvalidWebhookSignature('Missing signature');
        }

        if (! hash_equals(hash_hmac('sha256', $body, $serverKey), $signature)) {
            throw new InvalidWebhookSignature('Signature mismatch');
        }

        $event = json_decode($body, true);

        if (! is_array($event) || ! is_string($event['tran_ref'] ?? null) || $event['tran_ref'] === '') {
            throw new InvalidWebhookSignature('Malformed event');
        }

        $isRefund = mb_strtolower((string) ($event['tran_type'] ?? '')) === 'refund';
        $result = (string) Arr::get($event, 'payment_result.response_status', '');

        return new GatewayWebhookEvent(
            eventId: $event['tran_ref'].':'.($result !== '' ? $result : 'unknown'),
            type: 'transaction.'.mb_strtolower((string) ($event['tran_type'] ?? 'sale')),
            gatewayPaymentId: $isRefund ? null : $event['tran_ref'],
            paymentUlid: ! $isRefund && is_string($event['cart_id'] ?? null) ? $event['cart_id'] : null,
            payload: array_filter([
                ...Arr::only($event, ['tran_ref', 'previous_tran_ref', 'tran_type', 'cart_id', 'cart_currency', 'cart_amount', 'tran_currency', 'tran_total']),
                'response_status' => $result !== '' ? $result : null,
            ], fn ($value): bool => $value !== null),
            concernsPayment: ! $isRefund,
            gatewayRefundId: $isRefund ? $event['tran_ref'] : null,
        );
    }

    public function refund(Payment $payment, int $amountMinor): GatewayRefund
    {
        if (! is_string($payment->gateway_payment_id) || $payment->gateway_payment_id === '') {
            throw new PaymentGatewayException('Payment has no PayTabs transaction');
        }

        $response = $this->post('/payment/request', [
            'profile_id' => $this->profileId(),
            'tran_type' => 'refund',
            'tran_class' => 'ecom',
            'tran_ref' => $payment->gateway_payment_id,
            // Unique per refund so a retried approval is not mistaken for a new cart.
            'cart_id' => Str::limit('RF-'.$payment->ulid.'-'.($payment->refunded_minor + $amountMinor), 64, ''),
            'cart_currency' => mb_strtoupper($payment->currency),
            'cart_amount' => (float) self::decimal($amountMinor),
            'cart_description' => Str::limit('Refund '.$payment->reference, 120, ''),
        ]);

        return $this->refundResult($response->json(), $amountMinor);
    }

    public function refundStatus(Refund $refund): GatewayRefund
    {
        if (! is_string($refund->gateway_refund_id) || $refund->gateway_refund_id === '') {
            throw new PaymentGatewayException('Refund has no PayTabs transaction');
        }

        return $this->refundResult($this->query($refund->gateway_refund_id), $refund->amount_minor);
    }

    public function testConnection(): void
    {
        $profileId = $this->profileId();
        $serverKey = $this->requireSetting('paytabs_server_key');

        try {
            $response = $this->http()->withHeaders(['authorization' => $serverKey])
                ->post($this->url('/payment/query'), ['profile_id' => $profileId, 'tran_ref' => 'TST'.Str::upper(Str::random(12))]);
        } catch (Throwable) {
            throw new PaymentGatewayException('Connection failed or timed out');
        }

        $message = mb_strtolower((string) $response->json('message', ''));

        if (in_array($response->status(), [401, 403], true) || str_contains($message, 'authenticat') || str_contains($message, 'profile')) {
            throw new PaymentGatewayException('Authentication failed');
        }

        if ($response->serverError() || ! is_array($response->json())) {
            throw new PaymentGatewayException('HTTP '.$response->status());
        }
    }

    /**
     * @return array<string, mixed>
     *
     * @throws PaymentGatewayException
     */
    private function query(string $tranRef): array
    {
        $transaction = $this->post('/payment/query', ['profile_id' => $this->profileId(), 'tran_ref' => $tranRef])->json();

        if (! is_array($transaction) || ($transaction['tran_ref'] ?? null) !== $tranRef) {
            throw new PaymentGatewayException('Unexpected query response');
        }

        return $transaction;
    }

    /**
     * @param  mixed  $transaction  decoded refund transaction
     */
    private function refundResult(mixed $transaction, int $amountMinor): GatewayRefund
    {
        if (! is_array($transaction) || ! is_string($transaction['tran_ref'] ?? null)) {
            throw new PaymentGatewayException('Unexpected refund response');
        }

        $status = match ((string) Arr::get($transaction, 'payment_result.response_status', '')) {
            'A' => GatewayRefund::SUCCEEDED,
            'P', 'H' => GatewayRefund::PENDING,
            default => GatewayRefund::FAILED,
        };

        return new GatewayRefund(
            $transaction['tran_ref'],
            $status,
            isset($transaction['cart_amount']) ? self::minorFromDecimal($transaction['cart_amount']) : $amountMinor,
            $status === GatewayRefund::FAILED ? Str::limit((string) (Arr::get($transaction, 'payment_result.response_message') ?: 'refund_declined'), 100, '') : null,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function post(string $path, array $data): Response
    {
        $serverKey = $this->requireSetting('paytabs_server_key');

        return $this->call(fn () => $this->http()->withHeaders(['authorization' => $serverKey])->post($this->url($path), $data), 'code');
    }

    private function profileId(): int
    {
        $profileId = $this->requireSetting('paytabs_profile_id');

        return ctype_digit($profileId) ? (int) $profileId : throw new PaymentGatewayException('Requires credentials');
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
        return rtrim((string) ($this->config['regions'][$this->region()]['base_url'] ?? 'https://secure-egypt.paytabs.com'), '/').$path;
    }
}
