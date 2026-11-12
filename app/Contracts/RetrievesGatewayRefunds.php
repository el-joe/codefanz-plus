<?php

namespace App\Contracts;

use App\Exceptions\PaymentGatewayException;
use App\Models\Refund;
use App\Services\Payments\GatewayRefund;

/**
 * Optional for payment gateway drivers whose refunds can finish asynchronously: ask the gateway API for a
 * refund's real status. Refund webhooks never complete a refund from the delivery body alone.
 */
interface RetrievesGatewayRefunds
{
    /**
     * @throws PaymentGatewayException
     */
    public function refundStatus(Refund $refund): GatewayRefund;
}
