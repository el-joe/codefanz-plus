<?php

namespace App\Models;

use App\Enums\CustomerUserRole;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Membership of a portal user in a customer account. A revoked membership keeps its row for the audit trail.
 */
class CustomerUser extends Pivot
{
    public $incrementing = true;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => CustomerUserRole::class,
            'invited_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }
}
