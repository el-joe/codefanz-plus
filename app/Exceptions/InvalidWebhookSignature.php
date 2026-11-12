<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A webhook delivery failed authentication (missing/invalid signature, stale timestamp, unconfigured secret).
 */
class InvalidWebhookSignature extends RuntimeException {}
