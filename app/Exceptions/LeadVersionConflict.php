<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * The lead changed since the editor loaded it (optimistic locking via lock_version).
 */
class LeadVersionConflict extends RuntimeException
{
    public function __construct(public int $currentVersion)
    {
        parent::__construct('The lead was changed by someone else.');
    }
}
