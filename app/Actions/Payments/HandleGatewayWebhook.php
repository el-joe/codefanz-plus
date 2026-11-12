<?php

namespace App\Actions\Payments;

use App\Enums\PaymentEventSource;
use App\Exceptions\InvalidWebhookSignature;
use App\Exceptions\PaymentGatewayException;
use App\Models\Payment;
use App\Models\WebhookEvent;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Receives a gateway webhook: authenticate it, record it once in webhook_events (UNIQUE gateway + event id makes
 * duplicates no-ops), then hand the payment to VerifyPayment, which re-checks the status with the gateway API.
 * The webhook body itself is never trusted to mark anything paid.
 *
 * Outcomes: processed, duplicate, ignored (not about a known payment), invalid (bad signature), retry (the
 * gateway API could not be reached; the delivery is answered with 503 so the gateway sends it again).
 */
class HandleGatewayWebhook
{
    public const PROCESSED = 'processed';

    public const DUPLICATE = 'duplicate';

    public const IGNORED = 'ignored';

    public const INVALID = 'invalid';

    public const RETRY = 'retry';

    public function __construct(private PaymentGatewayManager $gateways, private VerifyPayment $verifyPayment) {}

    public function handle(string $gatewayKey, Request $request): string
    {
        $gateway = $this->gateways->driver($gatewayKey);

        try {
            $parsed = $gateway->parseWebhook($request);
        } catch (InvalidWebhookSignature $exception) {
            // Stored under a random id: an unauthenticated body must never reserve a real event id.
            WebhookEvent::create([
                'gateway' => $gatewayKey,
                'event_id' => 'unverified-'.Str::ulid(),
                'signature_valid' => false,
                'payload' => ['ip' => $request->ip(), 'length' => strlen($request->getContent())],
                'error' => Str::limit($exception->getMessage(), 250),
            ]);

            Log::warning('Rejected payment webhook.', ['gateway' => $gatewayKey, 'reason' => $exception->getMessage(), 'ip' => $request->ip()]);

            return self::INVALID;
        }

        try {
            $event = WebhookEvent::create([
                'gateway' => $gatewayKey,
                'event_id' => $parsed->eventId,
                'event_type' => Str::limit($parsed->type, 100, ''),
                'signature_valid' => true,
                'payload' => $parsed->payload,
            ]);
        } catch (UniqueConstraintViolationException) {
            $event = WebhookEvent::query()->where('gateway', $gatewayKey)->where('event_id', $parsed->eventId)->firstOrFail();

            if ($event->isProcessed()) {
                return self::DUPLICATE;
            }

            $event->forceFill(['attempts' => $event->attempts + 1])->save();
        }

        if (! $parsed->concernsPayment) {
            $event->forceFill(['processed_at' => now()])->save();

            return self::IGNORED;
        }

        $payment = $this->findPayment($gatewayKey, $parsed->gatewayPaymentId, $parsed->paymentUlid);

        if ($payment === null) {
            $event->forceFill(['processed_at' => now(), 'error' => 'No matching payment'])->save();

            return self::IGNORED;
        }

        try {
            $this->verifyPayment->handle($payment, PaymentEventSource::Webhook, $event);
        } catch (PaymentGatewayException $exception) {
            $event->forceFill(['error' => Str::limit($exception->getMessage(), 250)])->save();

            return self::RETRY;
        }

        $event->forceFill(['processed_at' => now(), 'error' => null])->save();

        return self::PROCESSED;
    }

    private function findPayment(string $gatewayKey, ?string $gatewayPaymentId, ?string $paymentUlid): ?Payment
    {
        if ($gatewayPaymentId !== null) {
            $payment = Payment::query()->where('gateway', $gatewayKey)->where('gateway_payment_id', $gatewayPaymentId)->first();

            if ($payment !== null) {
                return $payment;
            }
        }

        // A webhook can race the checkout response that stores the session id; the ulid we sent identifies it.
        if ($paymentUlid !== null && $gatewayPaymentId !== null) {
            $payment = Payment::query()->where('gateway', $gatewayKey)->where('ulid', $paymentUlid)->whereNull('gateway_payment_id')->first();

            if ($payment !== null) {
                $payment->forceFill(['gateway_payment_id' => $gatewayPaymentId])->save();

                return $payment;
            }
        }

        return null;
    }
}
