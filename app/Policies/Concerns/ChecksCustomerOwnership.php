<?php

namespace App\Policies\Concerns;

use App\Models\User;
use App\Services\CurrentCustomer;
use Illuminate\Database\Eloquent\Model;

/**
 * Policy helper for portal modules: a client may act on a record only when it belongs to the customer they
 * are currently acting for AND they still hold an active membership in that customer.
 */
trait ChecksCustomerOwnership
{
    /**
     * @param  Model  $record  A model using App\Models\Concerns\BelongsToCustomer.
     */
    protected function ownedByCurrentCustomer(User $user, Model $record): bool
    {
        $current = app(CurrentCustomer::class);

        if (! $current->has() || (int) $record->getAttribute('customer_id') !== $current->id()) {
            return false;
        }

        return $user->portalCustomers()->whereKey($current->id())->exists();
    }
}
