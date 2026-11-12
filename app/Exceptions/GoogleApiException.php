<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A Google API call that could not be made. `needsReconnect` marks credential/permission problems (show the
 * "reconnect" state) as opposed to temporary failures.
 */
class GoogleApiException extends RuntimeException
{
    public bool $needsReconnect = false;

    public static function notConfigured(): self
    {
        $exception = new self(__('Google credentials are not configured.'));
        $exception->needsReconnect = true;

        return $exception;
    }

    public static function invalidCredentials(string $message): self
    {
        $exception = new self($message);
        $exception->needsReconnect = true;

        return $exception;
    }

    public static function requestFailed(int $status): self
    {
        $exception = new self(__('Google API request failed (HTTP :status).', ['status' => $status]));
        $exception->needsReconnect = in_array($status, [401, 403], true);

        return $exception;
    }
}
