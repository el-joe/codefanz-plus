<?php

namespace App\Exceptions;

use App\Enums\AiUnavailableReason;
use RuntimeException;

/**
 * AI could not be used for this call: not configured, paused, over budget or the provider failed.
 * Callers fall back to a deterministic path.
 */
class AiUnavailable extends RuntimeException
{
    public function __construct(public readonly AiUnavailableReason $reason, ?\Throwable $previous = null)
    {
        parent::__construct('AI unavailable: '.$reason->value, previous: $previous);
    }
}
