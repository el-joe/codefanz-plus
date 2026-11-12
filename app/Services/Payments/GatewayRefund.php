<?php

namespace App\Services\Payments;

final readonly class GatewayRefund
{
    public function __construct(public string $gatewayRefundId, public string $status, public int $amountMinor) {}
}
