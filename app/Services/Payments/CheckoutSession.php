<?php

namespace App\Services\Payments;

/**
 * Result of PaymentGateway::createCheckout(): where to send the payer, and the gateway's id for the attempt.
 */
final readonly class CheckoutSession
{
    /**
     * @param  array<string, mixed>  $clientData  data for embedded flows (e.g. an iframe token); empty for redirects
     * @param  array<string, mixed>  $metadata  safe ids to keep on the payment (no card data)
     */
    public function __construct(
        public string $gatewayPaymentId,
        public ?string $redirectUrl,
        public array $clientData = [],
        public array $metadata = [],
    ) {}
}
