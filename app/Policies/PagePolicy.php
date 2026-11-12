<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Page;
use App\Models\User;

class PagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ContentManage->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ContentManage->value);
    }

    public function update(User $user, Page $page): bool
    {
        return $user->can(Permission::ContentManage->value);
    }

    public function publish(User $user, Page $page): bool
    {
        return $user->can(Permission::ContentManage->value);
    }

    public function delete(User $user, Page $page): bool
    {
        return $user->can(Permission::ContentManage->value);
    }
}
