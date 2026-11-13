<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A radar connector call that failed (missing credentials, HTTP error, unexpected response). Messages are short
 * and never contain tokens.
 */
class RadarConnectorException extends RuntimeException
{
    public function __construct(string $message, public readonly ?int $status = null)
    {
        parent::__construct(mb_substr($message, 0, 480));
    }
}
