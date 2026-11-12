<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A social platform call that failed. The kind decides what happens next:
 *  - transient: safe to retry (HTTP 429 / 5xx, or the connection never reached the platform);
 *  - unknown: the request may have reached the platform (timeout after sending) — never retried automatically;
 *  - auth: token expired or revoked (reconnect);
 *  - app_approval: the platform has not granted the app the permission (manual export until approved);
 *  - permanent: the platform rejected the content (fix and retry by hand).
 * Messages are short and never contain tokens.
 */
class SocialApiException extends RuntimeException
{
    public const TRANSIENT = 'transient';

    public const UNKNOWN = 'unknown';

    public const AUTH = 'auth';

    public const APP_APPROVAL = 'app_approval';

    public const PERMANENT = 'permanent';

    public function __construct(string $message, public readonly string $kind = self::PERMANENT, public readonly ?int $status = null)
    {
        parent::__construct(mb_substr($message, 0, 900));
    }

    public function isTransient(): bool
    {
        return $this->kind === self::TRANSIENT;
    }
}
