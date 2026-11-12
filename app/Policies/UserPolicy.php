<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(Permission::UsersManage->value);
    }

    public function create(User $actor): bool
    {
        return $actor->can(Permission::UsersManage->value);
    }

    /**
     * Only a Super Admin may change another Super Admin's account.
     */
    public function update(User $actor, User $user): bool
    {
        if (! $actor->can(Permission::UsersManage->value)) {
            return false;
        }

        return ! $user->hasRole(Role::SuperAdmin->value) || $actor->hasRole(Role::SuperAdmin->value);
    }

    /**
     * Resetting two-factor is for a colleague who lost their device, never for yourself.
     */
    public function resetTwoFactor(User $actor, User $user): bool
    {
        return $actor->isNot($user) && $this->update($actor, $user);
    }
}
