<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Project;
use App\Models\User;

/**
 * Admin side of delivery: projects.view reads projects (milestones, notes, documents, conversations);
 * projects.manage changes them. Portal access is scoped by customer in the portal controllers instead.
 */
class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ProjectsView->value) || $user->can(Permission::ProjectsManage->value);
    }

    public function view(User $user, Project $project): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ProjectsManage->value);
    }

    /**
     * Edit details, milestones, notes, documents and post in conversations.
     */
    public function update(User $user, Project $project): bool
    {
        return $user->can(Permission::ProjectsManage->value);
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->can(Permission::ProjectsManage->value);
    }
}
