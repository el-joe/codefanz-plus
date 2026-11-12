<?php

namespace App\Exceptions;

/**
 * The gateway has no API refund for this payment (e.g. cash paid at a Fawry outlet): finance must return the
 * money outside the gateway and record the external reference. Nothing was sent to the gateway.
 */
class ManualRefundRequired extends PaymentGatewayException {}
