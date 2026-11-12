<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Contracts\TestsGatewayConnection;
use App\Enums\GatewayMode;
use App\Enums\PaymentStatus;
use App\Exceptions\InvalidWebhookSignature;
use App\Exceptions\ManualRefundRequired;
use App\Exceptions\PaymentGatewayException;
use App\Models\Payment;
use App\Models\PaymentLink;
use App\Services\Payments\Concerns\CallsGatewayApi;
use App\Services\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * FawryPay (Egypt, EGP) over Laravel's HTTP client. Staging (atfawry.fawrystaging.com) in test mode, production
 * (www.atfawry.com) in live mode. Every signature is a plain SHA-256 hex digest of the documented fields followed
 * by the secure key; amounts are signed with exactly two decimals ("1500.00").
 *
 * - Reference number (pay at a Fawry outlet, default): POST /ECommerceWeb/Fawry/payments/charge with
 *   paymentMethod PAYATFAWRY, signature = merchantCode + merchantRefNum + customerProfileId + paymentMethod +
 *   amount + secureKey (FawryPay docs → Server APIs → Create charge → PayAtFawry). The payer gets a reference
 *   number with an expiry and pays later, so the payment stays pending until a verified notification arrives.
 * - Card (hosted): POST /fawrypay-api/api/payments/init, signature = merchantCode + merchantRefNum +
 *   customerProfileId + returnUrl + itemId + quantity + price + secureKey (FawryPay Express Checkout → init).
 * - Verification: GET /ECommerceWeb/Fawry/payments/status/v2, signature = merchantCode + merchantRefNumber +
 *   secureKey (FawryPay docs → Get payment status V2).
 * - Server notification V2: messageSignature = fawryRefNumber + merchantRefNumber + paymentAmount + orderAmount +
 *   orderStatus + paymentMethod + paymentRefrenceNumber + secureKey (FawryPay docs → Server notification V2).
 * - Refunds: POST /ECommerceWeb/Fawry/payments/refund, signature = merchantCode + referenceNumber + refundAmount +
 *   reason + secureKey (FawryPay docs → Refund API). Cash paid at an outlet is refunded manually.
 * - Connection test: a status query for a random merchant reference (no side effects).
 *
 * Credentials (Settings → Payments): fawry_merchant_code, fawry_secure_key, fawry_mode, fawry_payment_method.
 */
class FawryGateway implements PaymentGateway, TestsGatewayConnection
{
    use CallsGatewayApi;

    public const PAY_AT_FAWRY = 'PAYATFAWRY';

    public const CARD = 'CARD';

    /**
     * Fawry order statuses (status API and notification V2).
     */
    private const STATUS_MAP = [
        'PAID' => PaymentStatus::Paid,
        'NEW' => PaymentStatus::Pending,
        'UNPAID' => PaymentStatus::Pending,
        'EXPIRED' => PaymentStatus::Cancelled,
        'CANCELED' => PaymentStatus::Cancelled,
        'CANCELLED' => PaymentStatus::Cancelled,
        'FAILED' => PaymentStatus::Failed,
    ];

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(private Settings $settings, private string $key = 'fawry', private array $config = []) {}

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
        return GatewayMode::tryFrom((string) $this->setting('fawry_mode')) ?? GatewayMode::Test;
    }

    public function supportsCurrency(string $currency): bool
    {
        return $this->supportsListedCurrency($currency, (array) ($this->config['currencies'] ?? []));
    }

    public function paymentMethod(): string
    {
        return $this->setting('fawry_payment_method') === self::CARD ? self::CARD : self::PAY_AT_FAWRY;
    }

    public function createCheckout(PaymentLink $link, Payment $payment): CheckoutSession
    {
        $payment->loadMissing('customer');

        return $this->paymentMethod() === self::CARD
            ? $this->createHostedCheckout($link, $payment)
            : $this->createReferenceCharge($link, $payment);
    }

    public function verifyPayment(Payment $payment): GatewayVerification
    {
        $merchantCode = $this->requireSetting('fawry_merchant_code');
        $reference = (string) $payment->gateway_payment_id;

        $status = $this->call(fn () => $this->http()->get($this->url('/ECommerceWeb/Fawry/payments/status/v2'), [
            'merchantCode' => $merchantCode,
            'merchantRefNumber' => $reference,
            'signature' => $this->sign([$merchantCode, $reference]),
        ]))->json();

        if (! is_array($status) || (int) ($status['statusCode'] ?? 0) !== 200) {
            throw new PaymentGatewayException('Status '.Str::limit((string) (is_array($status) ? ($status['statusCode'] ?? 'unknown') : 'unknown'), 20, ''));
        }

        if (($status['merchantRefNumber'] ?? $reference) !== $reference) {
            throw new PaymentGatewayException('Status belongs to another order');
        }

        $orderStatus = mb_strtoupper((string) ($status['orderStatus'] ?? ''));
        $mapped = self::STATUS_MAP[$orderStatus] ?? PaymentStatus::Pending;

        return new GatewayVerification(
            status: $mapped,
            // orderAmount is the merchant's amount; paymentAmount may include Fawry's customer fees.
            amountMinor: self::minorFromDecimal($status['orderAmount'] ?? $status['paymentAmount'] ?? 0),
            currency: 'EGP',
            paidAt: $mapped === PaymentStatus::Paid && is_numeric($status['paymentTime'] ?? null)
                ? CarbonImmutable::createFromTimestampMs((int) $status['paymentTime'])
                : null,
            metadata: array_filter([
                'fawry_reference_number' => is_scalar($status['referenceNumber'] ?? null) ? (string) $status['referenceNumber'] : null,
                'fawry_order_status' => $orderStatus !== '' ? $orderStatus : null,
                'fawry_payment_method' => is_string($status['paymentMethod'] ?? null) ? $status['paymentMethod'] : null,
                'fawry_fees_minor' => isset($status['fawryFees']) ? self::minorFromDecimal($status['fawryFees']) : null,
                'payment_method_type' => match ($status['paymentMethod'] ?? null) {
                    self::PAY_AT_FAWRY => 'fawry_outlet',
                    self::CARD => 'card',
                    'MWALLET' => 'wallet',
                    default => null,
                },
            ], fn ($value): bool => $value !== null),
            failureReason: match ($mapped) {
                PaymentStatus::Cancelled => mb_strtolower($orderStatus) ?: 'cancelled',
                PaymentStatus::Failed => 'declined',
                default => null,
            },
        );
    }

    public function parseWebhook(Request $request): GatewayWebhookEvent
    {
        $secureKey = $this->setting('fawry_secure_key') ?? throw new InvalidWebhookSignature('Secure key not configured');
        $event = json_decode($request->getContent(), true);

        if (! is_array($event) || ! is_string($event['messageSignature'] ?? null) || $event['messageSignature'] === '') {
            throw new InvalidWebhookSignature('Missing signature');
        }

        if (! hash_equals(self::notificationSignature($event, $secureKey), mb_strtolower($event['messageSignature']))) {
            throw new InvalidWebhookSignature('Signature mismatch');
        }

        $merchantReference = $event['merchantRefNumber'] ?? $event['merchantRefNum'] ?? null;
        $fawryReference = $event['fawryRefNumber'] ?? null;

        if (! is_string($merchantReference) || $merchantReference === '' || ! is_scalar($fawryReference)) {
            throw new InvalidWebhookSignature('Malformed event');
        }

        $orderStatus = mb_strtoupper((string) ($event['orderStatus'] ?? ''));

        return new GatewayWebhookEvent(
            eventId: $fawryReference.':'.($orderStatus !== '' ? $orderStatus : 'UNKNOWN'),
            type: 'order.'.mb_strtolower($orderStatus !== '' ? $orderStatus : 'unknown'),
            gatewayPaymentId: $merchantReference,
            paymentUlid: $merchantReference,
            payload: array_filter([
                ...Arr::only($event, ['requestId', 'fawryRefNumber', 'merchantRefNumber', 'orderStatus', 'paymentMethod', 'paymentAmount', 'orderAmount', 'fawryFees', 'paymentTime', 'orderExpiryDate']),
            ], fn ($value): bool => $value !== null),
            concernsPayment: true,
        );
    }

    public function refund(Payment $payment, int $amountMinor): GatewayRefund
    {
        // Cash handed over at a Fawry outlet cannot be returned through the API.
        if (($payment->metadata['fawry_payment_method'] ?? null) === self::PAY_AT_FAWRY) {
            throw new ManualRefundRequired('Fawry outlet payments are refunded manually');
        }

        $merchantCode = $this->requireSetting('fawry_merchant_code');
        $reference = $payment->metadata['fawry_reference_number'] ?? null;

        if (! is_string($reference) || $reference === '') {
            throw new PaymentGatewayException('Payment has no Fawry reference');
        }

        $amount = self::decimal($amountMinor);
        $reason = 'Refund '.$payment->reference;

        $response = $this->call(fn () => $this->http()->post($this->url('/ECommerceWeb/Fawry/payments/refund'), [
            'merchantCode' => $merchantCode,
            'referenceNumber' => $reference,
            'refundAmount' => (float) $amount,
            'reason' => $reason,
            'signature' => $this->sign([$merchantCode, $reference, $amount, $reason]),
        ]))->json();

        $succeeded = is_array($response) && (int) ($response['statusCode'] ?? 0) === 200;

        // Fawry answers without a refund id; the cumulative refunded amount makes each refund unique per payment.
        return new GatewayRefund(
            $reference.'-'.($payment->refunded_minor + $amountMinor),
            $succeeded ? GatewayRefund::SUCCEEDED : GatewayRefund::FAILED,
            $amountMinor,
            $succeeded ? null : Str::limit((string) (is_array($response) ? ($response['statusDescription'] ?? 'refund_declined') : 'refund_declined'), 100, ''),
        );
    }

    public function testConnection(): void
    {
        $merchantCode = $this->requireSetting('fawry_merchant_code');
        $reference = 'CONNTEST'.Str::upper(Str::random(12));

        $status = $this->call(fn () => $this->http()->get($this->url('/ECommerceWeb/Fawry/payments/status/v2'), [
            'merchantCode' => $merchantCode,
            'merchantRefNumber' => $reference,
            'signature' => $this->sign([$merchantCode, $reference]),
        ]))->json();

        if (! is_array($status) || ! isset($status['statusCode'])) {
            throw new PaymentGatewayException('Unexpected status response');
        }

        // A random reference is "not found"; only merchant or signature errors mean the credentials are wrong.
        $description = mb_strtolower((string) ($status['statusDescription'] ?? ''));

        if (str_contains($description, 'signature') || str_contains($description, 'merchant')) {
            throw new PaymentGatewayException('Credentials rejected ('.Str::limit((string) $status['statusCode'], 10, '').')');
        }
    }

    /**
     * The documented server notification V2 signature.
     *
     * @param  array<string, mixed>  $event
     */
    public static function notificationSignature(array $event, string $secureKey): string
    {
        $amount = fn (mixed $value): string => is_numeric($value) ? number_format((float) $value, 2, '.', '') : '';

        return hash('sha256', implode('', [
            (string) ($event['fawryRefNumber'] ?? ''),
            (string) ($event['merchantRefNumber'] ?? $event['merchantRefNum'] ?? ''),
            $amount($event['paymentAmount'] ?? null),
            $amount($event['orderAmount'] ?? null),
            (string) ($event['orderStatus'] ?? ''),
            (string) ($event['paymentMethod'] ?? ''),
            (string) ($event['paymentRefrenceNumber'] ?? ''),
            $secureKey,
        ]));
    }

    private function createReferenceCharge(PaymentLink $link, Payment $payment): CheckoutSession
    {
        $merchantCode = $this->requireSetting('fawry_merchant_code');
        $profileId = (string) $payment->customer_id;
        $amount = self::decimal($payment->amount_minor);
        $expiresAt = now()->addHours((int) ($this->config['reference_expiry_hours'] ?? 48));

        $response = $this->call(fn () => $this->http()->post($this->url('/ECommerceWeb/Fawry/payments/charge'), array_filter([
            'merchantCode' => $merchantCode,
            'merchantRefNum' => $payment->ulid,
            'customerProfileId' => $profileId,
            'customerName' => $payment->customer->contact_name ?: $payment->customer->company_name,
            'customerMobile' => $payment->customer->phone,
            'customerEmail' => $payment->customer->email,
            'paymentMethod' => self::PAY_AT_FAWRY,
            'amount' => (float) $amount,
            'currencyCode' => 'EGP',
            'language' => app()->getLocale() === 'ar' ? 'ar-eg' : 'en-gb',
            'description' => Str::limit($link->description ?: (string) $link->reference, 120, ''),
            'paymentExpiry' => $expiresAt->getTimestampMs(),
            'chargeItems' => [['itemId' => (string) $link->reference, 'description' => Str::limit($link->description ?: (string) $link->reference, 120, ''), 'price' => (float) $amount, 'quantity' => 1]],
            'signature' => $this->sign([$merchantCode, $payment->ulid, $profileId, self::PAY_AT_FAWRY, $amount]),
        ], fn ($value): bool => $value !== null && $value !== '')))->json();

        if (! is_array($response) || (int) ($response['statusCode'] ?? 0) !== 200 || ! is_scalar($response['referenceNumber'] ?? null)) {
            throw new PaymentGatewayException('Charge refused'.(is_array($response) && isset($response['statusCode']) ? ' '.Str::limit((string) $response['statusCode'], 10, '') : ''));
        }

        $expiry = is_numeric($response['expirationTime'] ?? null) ? CarbonImmutable::createFromTimestampMs((int) $response['expirationTime']) : $expiresAt->toImmutable();

        // No redirect to Fawry: the return page shows the reference number and its expiry.
        return new CheckoutSession(
            $payment->ulid,
            route('pay.return', ['paymentLink' => $link, 'payment' => $payment]),
            metadata: [
                'fawry_payment_method' => self::PAY_AT_FAWRY,
                'fawry_reference_number' => (string) $response['referenceNumber'],
                'offline_reference' => (string) $response['referenceNumber'],
                'offline_reference_expires_at' => $expiry->toIso8601String(),
            ],
        );
    }

    private function createHostedCheckout(PaymentLink $link, Payment $payment): CheckoutSession
    {
        $merchantCode = $this->requireSetting('fawry_merchant_code');
        $profileId = (string) $payment->customer_id;
        $amount = self::decimal($payment->amount_minor);
        $itemId = (string) $link->reference;
        $returnUrl = route('pay.return', ['paymentLink' => $link, 'payment' => $payment]);

        $response = $this->call(fn () => $this->http()->post($this->url('/fawrypay-api/api/payments/init'), array_filter([
            'merchantCode' => $merchantCode,
            'merchantRefNum' => $payment->ulid,
            'customerProfileId' => $profileId,
            'customerName' => $payment->customer->contact_name ?: $payment->customer->company_name,
            'customerMobile' => $payment->customer->phone,
            'customerEmail' => $payment->customer->email,
            'language' => app()->getLocale() === 'ar' ? 'ar-eg' : 'en-gb',
            'paymentMethod' => self::CARD,
            'returnUrl' => $returnUrl,
            'authCaptureModePayment' => false,
            'chargeItems' => [['itemId' => $itemId, 'description' => Str::limit($link->description ?: $itemId, 120, ''), 'price' => (float) $amount, 'quantity' => 1]],
            'signature' => $this->sign([$merchantCode, $payment->ulid, $profileId, $returnUrl, $itemId, '1', $amount]),
        ], fn ($value): bool => $value !== null && $value !== '')));

        $url = trim($response->body(), "\" \n\r\t");

        if (! str_starts_with($url, 'https://')) {
            throw new PaymentGatewayException('Unexpected init response');
        }

        return new CheckoutSession($payment->ulid, $url, metadata: ['fawry_payment_method' => self::CARD]);
    }

    /**
     * SHA-256 of the given fields followed by the secure key.
     *
     * @param  list<string>  $fields
     */
    private function sign(array $fields): string
    {
        return hash('sha256', implode('', $fields).$this->requireSetting('fawry_secure_key'));
    }

    private function url(string $path): string
    {
        $base = $this->mode() === GatewayMode::Live
            ? ($this->config['live_base_url'] ?? 'https://www.atfawry.com')
            : ($this->config['test_base_url'] ?? 'https://atfawry.fawrystaging.com');

        return rtrim((string) $base, '/').$path;
    }
}
