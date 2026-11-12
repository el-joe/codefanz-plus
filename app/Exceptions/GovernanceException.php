<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when a governance rule (approval, permission, version, kill switch) blocks an operation.
 */
class GovernanceException extends RuntimeException
{
    /**
     * Whether the operation may succeed later without changing the request (e.g. a paused kill switch).
     */
    public bool $retryable = false;

    public static function killSwitchActive(string $key): self
    {
        $exception = new self("Operation blocked: the {$key} kill switch is active.");
        $exception->retryable = true;

        return $exception;
    }

    public static function notReviewable(string $status): self
    {
        return new self("This change request cannot be reviewed while it is {$status}.");
    }

    public static function selfReview(): self
    {
        return new self('You cannot review a change you proposed.');
    }

    public static function missingPermission(string $permission): self
    {
        return new self("The {$permission} permission is required.");
    }

    public static function notApproved(): self
    {
        return new self('Only approved change requests can be applied.');
    }

    public static function expired(): self
    {
        return new self('This change request has expired.');
    }

    public static function staleTarget(int $expected, int $actual): self
    {
        return new self("The target changed since the proposal (expected version {$expected}, found {$actual}).");
    }

    public static function disallowedAttributes(array $attributes): self
    {
        return new self('These attributes cannot be changed through a change request: '.implode(', ', $attributes).'.');
    }
}
