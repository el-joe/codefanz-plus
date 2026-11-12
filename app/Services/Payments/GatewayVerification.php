<?php

namespace App\Services\Payments;

use App\Enums\PaymentStatus;
use Carbon\CarbonImmutable;

/**
 * What the gateway's API says about a payment, fetched server-side by PaymentGateway::verifyPayment().
 */
final readonly class GatewayVerification
{
    /**
     * @param  array<string, mixed>  $metadata  gateway ids and statuses only (never card data)
     */
    public function __construct(
        public PaymentStatus $status,
        public int $amountMinor,
        public string $currency,
        public ?int $feeMinor = null,
        public ?CarbonImmutable $paidAt = null,
        public array $metadata = [],
        public ?string $failureReason = null,
    ) {}
}
