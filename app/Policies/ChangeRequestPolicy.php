<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\ChangeRequest;
use App\Models\User;

class ChangeRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(array_map(fn (Permission $p): string => $p->value, Permission::approvalPermissions()));
    }

    public function view(User $user, ChangeRequest $changeRequest): bool
    {
        return $this->viewAny($user);
    }

    public function review(User $user, ChangeRequest $changeRequest): bool
    {
        return $user->can($changeRequest->required_permission->value)
            && $changeRequest->requested_by !== $user->getKey();
    }

    public function apply(User $user, ChangeRequest $changeRequest): bool
    {
        return $user->can($changeRequest->required_permission->value);
    }
}
