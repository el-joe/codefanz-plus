<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A gateway call failed or the gateway is not usable. Messages are short and safe to store ("HTTP 502",
 * "Requires credentials"): never secrets, card data or full response bodies.
 */
class PaymentGatewayException extends RuntimeException {}
