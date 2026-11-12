<?php

namespace App\Contracts;

use App\Exceptions\PaymentGatewayException;

/**
 * Optional for payment gateway drivers: a harmless authenticated API call (no charge, no side effects) proving
 * the saved credentials work. Settings → Payments shows "Connected (verified <date>)" only after it succeeded,
 * and the verification is cleared whenever the gateway's credentials change.
 */
interface TestsGatewayConnection
{
    /**
     * @throws PaymentGatewayException with a short, secret-free reason when the call fails
     */
    public function testConnection(): void;
}
