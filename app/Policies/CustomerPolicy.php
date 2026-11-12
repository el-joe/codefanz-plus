<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Customer;
use App\Models\User;

/**
 * customers.view reads customer accounts; customers.manage creates, edits, converts leads and manages portal access.
 */
class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::CustomersView->value) || $user->can(Permission::CustomersManage->value);
    }

    public function view(User $user, Customer $customer): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::CustomersManage->value);
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->can(Permission::CustomersManage->value);
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->can(Permission::CustomersManage->value);
    }

    /**
     * Invite portal users, resend invitations, switch access off and on.
     */
    public function managePortalAccess(User $user, Customer $customer): bool
    {
        return $user->can(Permission::CustomersManage->value);
    }
}
