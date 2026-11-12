<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Catalog content (services, technologies, industries…) is managed by anyone holding content.manage.
 */
abstract class ContentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ContentManage->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ContentManage->value);
    }

    public function update(User $user, Model $model): bool
    {
        return $user->can(Permission::ContentManage->value);
    }

    public function publish(User $user, Model $model): bool
    {
        return $user->can(Permission::ContentManage->value);
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->can(Permission::ContentManage->value);
    }
}
