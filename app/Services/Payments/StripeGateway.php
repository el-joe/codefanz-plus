<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Enums\GatewayMode;
use App\Enums\PaymentStatus;
use App\Exceptions\InvalidWebhookSignature;
use App\Exceptions\PaymentGatewayException;
use App\Models\Payment;
use App\Models\PaymentLink;
use App\Services\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Stripe Checkout through the REST API with Laravel's HTTP client (no SDK).
 *
 * - Checkout: POST /v1/checkout/sessions (hosted page), idempotency key per payment.
 * - Verification: GET /v1/checkout/sessions/{id} expanded to the charge's balance transaction for the fee.
 * - Webhooks: `Stripe-Signature: t=…,v1=…` is HMAC-SHA256 of "{t}.{raw body}" with the endpoint's signing
 *   secret; deliveries outside the timestamp tolerance are rejected (replay protection).
 *
 * Credentials (Settings → Payments): stripe_secret_key, stripe_webhook_secret, stripe_mode.
 */
class StripeGateway implements PaymentGateway
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(private Settings $settings, private string $key = 'stripe', private array $config = []) {}

    public function key(): string
    {
        return $this->key;
    }

    public function isConfigured(): bool
    {
        return $this->secretKey() !== null && $this->webhookSecret() !== null;
    }

    public function mode(): GatewayMode
    {
        $mode = $this->settings->get('payments', 'stripe_mode');

        if (is_string($mode) && GatewayMode::tryFrom($mode) !== null) {
            return GatewayMode::from($mode);
        }

        return str_contains((string) $this->secretKey(), '_live_') ? GatewayMode::Live : GatewayMode::Test;
    }

    public function supportsCurrency(string $currency): bool
    {
        return in_array(mb_strtoupper($currency), (array) ($this->config['currencies'] ?? []), true);
    }

    public function createCheckout(PaymentLink $link, Payment $payment): CheckoutSession
    {
        $name = $link->description ?: __('Payment :reference', ['reference' => $link->reference]);

        $response = $this->send('post', '/v1/checkout/sessions', [
            'mode' => 'payment',
            'client_reference_id' => $payment->ulid,
            'success_url' => route('pay.return', ['paymentLink' => $link, 'payment' => $payment]),
            'cancel_url' => route('pay.show', ['paymentLink' => $link, 'cancelled' => 1]),
            'locale' => 'auto',
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => mb_strtolower($payment->currency),
                    'unit_amount' => $payment->amount_minor,
                    'product_data' => ['name' => Str::limit($name, 250, '')],
                ],
            ]],
            'metadata' => ['payment_ulid' => $payment->ulid, 'payment_reference' => (string) $payment->reference, 'link_reference' => (string) $link->reference],
            'payment_intent_data' => ['metadata' => ['payment_ulid' => $payment->ulid, 'payment_reference' => (string) $payment->reference]],
        ], idempotencyKey: 'checkout-'.$payment->ulid);

        $id = $response->json('id');
        $url = $response->json('url');

        if (! is_string($id) || ! is_string($url) || ! str_starts_with($url, 'https://')) {
            throw new PaymentGatewayException('Unexpected checkout response');
        }

        return new CheckoutSession($id, $url, metadata: ['checkout_session_id' => $id, 'livemode' => (bool) $response->json('livemode')]);
    }

    public function verifyPayment(Payment $payment): GatewayVerification
    {
        if (! is_string($payment->gateway_payment_id) || $payment->gateway_payment_id === '') {
            throw new PaymentGatewayException('Payment has no checkout session');
        }

        $session = $this->send('get', '/v1/checkout/sessions/'.rawurlencode($payment->gateway_payment_id), [
            'expand' => ['payment_intent.latest_charge.balance_transaction'],
        ])->json();

        if (! is_array($session) || ($session['id'] ?? null) !== $payment->gateway_payment_id) {
            throw new PaymentGatewayException('Unexpected session response');
        }

        $intent = is_array($session['payment_intent'] ?? null) ? $session['payment_intent'] : [];
        $charge = is_array($intent['latest_charge'] ?? null) ? $intent['latest_charge'] : [];
        $balance = is_array($charge['balance_transaction'] ?? null) ? $charge['balance_transaction'] : [];
        $currency = mb_strtoupper((string) ($session['currency'] ?? ''));

        $status = match (true) {
            ($session['status'] ?? null) === 'complete' && ($session['payment_status'] ?? null) === 'paid' => PaymentStatus::Paid,
            ($session['status'] ?? null) === 'expired' => PaymentStatus::Cancelled,
            ($session['status'] ?? null) === 'complete' && in_array($intent['status'] ?? null, ['requires_payment_method', 'canceled'], true) => PaymentStatus::Failed,
            default => PaymentStatus::Pending,
        };

        // The fee is only meaningful when Stripe settled in the payment's currency.
        $fee = isset($balance['fee']) && mb_strtoupper((string) ($balance['currency'] ?? '')) === $currency ? (int) $balance['fee'] : null;

        return new GatewayVerification(
            status: $status,
            amountMinor: (int) ($session['amount_total'] ?? 0),
            currency: $currency,
            feeMinor: $fee,
            paidAt: isset($charge['created']) ? CarbonImmutable::createFromTimestamp((int) $charge['created']) : null,
            metadata: array_filter([
                'checkout_session_id' => $session['id'],
                'payment_intent_id' => $intent['id'] ?? (is_string($session['payment_intent'] ?? null) ? $session['payment_intent'] : null),
                'charge_id' => $charge['id'] ?? null,
                'session_status' => $session['status'] ?? null,
                'payment_status' => $session['payment_status'] ?? null,
                'payment_method_type' => $charge['payment_method_details']['type'] ?? null,
                'livemode' => (bool) ($session['livemode'] ?? false),
            ], fn ($value): bool => $value !== null),
            failureReason: $status === PaymentStatus::Failed
                ? Str::limit((string) ($intent['last_payment_error']['code'] ?? 'payment_failed'), 100, '')
                : ($status === PaymentStatus::Cancelled ? 'checkout_expired' : null),
        );
    }

    public function parseWebhook(Request $request): GatewayWebhookEvent
    {
        $secret = $this->webhookSecret() ?? throw new InvalidWebhookSignature('Webhook secret not configured');
        $header = (string) $request->header('Stripe-Signature', '');
        $body = $request->getContent();

        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $header) as $part) {
            [$name, $value] = array_pad(explode('=', trim($part), 2), 2, '');

            if ($name === 't' && ctype_digit($value)) {
                $timestamp = (int) $value;
            } elseif ($name === 'v1' && $value !== '') {
                $signatures[] = $value;
            }
        }

        if ($timestamp === null || $signatures === []) {
            throw new InvalidWebhookSignature('Missing signature');
        }

        if (abs(now()->getTimestamp() - $timestamp) > (int) config('codefanz.payments.webhook_tolerance_seconds', 300)) {
            throw new InvalidWebhookSignature('Timestamp outside tolerance');
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$body, $secret);

        if (! collect($signatures)->contains(fn (string $signature): bool => hash_equals($expected, $signature))) {
            throw new InvalidWebhookSignature('Signature mismatch');
        }

        $event = json_decode($body, true);

        if (! is_array($event) || ! is_string($event['id'] ?? null) || ! is_string($event['type'] ?? null)) {
            throw new InvalidWebhookSignature('Malformed event');
        }

        $object = is_array($event['data']['object'] ?? null) ? $event['data']['object'] : [];
        $isSession = ($object['object'] ?? null) === 'checkout.session';

        return new GatewayWebhookEvent(
            eventId: $event['id'],
            type: $event['type'],
            gatewayPaymentId: $isSession && is_string($object['id'] ?? null) ? $object['id'] : null,
            paymentUlid: $isSession && is_string($object['client_reference_id'] ?? null) ? $object['client_reference_id'] : null,
            payload: $this->sanitize($event, $object),
            concernsPayment: $isSession && str_starts_with($event['type'], 'checkout.session.'),
        );
    }

    public function refund(Payment $payment, int $amountMinor): GatewayRefund
    {
        $intent = $payment->metadata['payment_intent_id'] ?? null;

        if (! is_string($intent)) {
            throw new PaymentGatewayException('Payment has no payment intent');
        }

        $response = $this->send('post', '/v1/refunds', [
            'payment_intent' => $intent,
            'amount' => $amountMinor,
            'metadata' => ['payment_ulid' => $payment->ulid],
        ], idempotencyKey: 'refund-'.$payment->ulid.'-'.$amountMinor.'-'.$payment->refunded_minor);

        return new GatewayRefund((string) $response->json('id'), (string) $response->json('status'), (int) $response->json('amount'));
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws PaymentGatewayException
     */
    private function send(string $method, string $path, array $data, ?string $idempotencyKey = null): Response
    {
        $secret = $this->secretKey() ?? throw new PaymentGatewayException('Requires credentials');

        try {
            return $this->client($secret, $idempotencyKey)->{$method}(rtrim((string) ($this->config['base_url'] ?? 'https://api.stripe.com'), '/').$path, $data)->throw();
        } catch (RequestException $exception) {
            $code = $exception->response->json('error.code');

            throw new PaymentGatewayException('HTTP '.$exception->response->status().(is_string($code) ? ' '.Str::limit($code, 60, '') : ''), previous: $exception);
        } catch (ConnectionException $exception) {
            throw new PaymentGatewayException('Connection failed or timed out', previous: $exception);
        }
    }

    private function client(string $secret, ?string $idempotencyKey): PendingRequest
    {
        return Http::withToken($secret)
            ->asForm()
            ->acceptJson()
            ->withHeaders(array_filter([
                'Stripe-Version' => $this->config['api_version'] ?? null,
                'Idempotency-Key' => $idempotencyKey,
            ]))
            ->timeout((int) config('codefanz.payments.timeout_seconds', 20))
            ->connectTimeout((int) config('codefanz.payments.connect_timeout_seconds', 5));
    }

    /**
     * Keep only identifiers and statuses from the event (no customer details, no payment method details).
     *
     * @param  array<string, mixed>  $event
     * @param  array<string, mixed>  $object
     * @return array<string, mixed>
     */
    private function sanitize(array $event, array $object): array
    {
        return [
            ...Arr::only($event, ['id', 'type', 'created', 'livemode', 'api_version']),
            'object' => array_filter([
                ...Arr::only($object, ['id', 'object', 'status', 'payment_status', 'amount_total', 'currency', 'client_reference_id', 'mode']),
                'payment_intent' => is_array($object['payment_intent'] ?? null) ? ($object['payment_intent']['id'] ?? null) : ($object['payment_intent'] ?? null),
                'metadata' => is_array($object['metadata'] ?? null) ? Arr::only($object['metadata'], ['payment_ulid', 'payment_reference', 'link_reference']) : null,
            ], fn ($value): bool => $value !== null),
        ];
    }

    private function secretKey(): ?string
    {
        $key = $this->settings->get('payments', 'stripe_secret_key');

        return is_string($key) && $key !== '' ? $key : null;
    }

    private function webhookSecret(): ?string
    {
        $secret = $this->settings->get('payments', 'stripe_webhook_secret');

        return is_string($secret) && $secret !== '' ? $secret : null;
    }
}
