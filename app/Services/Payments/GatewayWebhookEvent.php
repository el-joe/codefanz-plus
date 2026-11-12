<?php

namespace App\Services\Payments;

/**
 * An authenticated webhook delivery. `gatewayPaymentId` and `paymentUlid` identify our payment when the event
 * is about one; `payload` is the sanitized body that gets stored in webhook_events.
 */
final readonly class GatewayWebhookEvent
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $eventId,
        public string $type,
        public ?string $gatewayPaymentId,
        public ?string $paymentUlid,
        public array $payload,
        public bool $concernsPayment,
    ) {}
}
