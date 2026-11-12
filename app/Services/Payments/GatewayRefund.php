<?php

namespace App\Services\Payments;

/**
 * Result of a gateway refund call. `status` is normalised by the driver to succeeded, pending or failed.
 */
final readonly class GatewayRefund
{
    public const SUCCEEDED = 'succeeded';

    public const PENDING = 'pending';

    public const FAILED = 'failed';

    public function __construct(public string $gatewayRefundId, public string $status, public int $amountMinor, public ?string $failureReason = null) {}

    public function succeeded(): bool
    {
        return $this->status === self::SUCCEEDED;
    }

    public function failed(): bool
    {
        return $this->status === self::FAILED;
    }
}
