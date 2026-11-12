<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\AiGeneration;
use App\Models\User;

/**
 * Generations are private to their author. Prompt managers (ai.manage) and reviewers who hold an approval
 * permission may read them (reviewers open them from the approval queue); only the author can use them.
 */
class AiGenerationPolicy
{
    public function view(User $user, AiGeneration $generation): bool
    {
        return $generation->user_id === $user->getKey()
            || $user->can(Permission::AiManage->value)
            || $user->hasAnyPermission(array_map(fn (Permission $permission): string => $permission->value, Permission::approvalPermissions()));
    }

    public function use(User $user, AiGeneration $generation): bool
    {
        return $generation->user_id === $user->getKey() && $user->can(Permission::AiUse->value);
    }
}
