<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Lead;
use App\Models\User;

/**
 * leads.view reads the CRM; leads.manage changes it (status, assignment, notes, details, new leads).
 */
class LeadPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::LeadsView->value) || $user->can(Permission::LeadsManage->value);
    }

    public function view(User $user, Lead $lead): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::LeadsManage->value);
    }

    public function update(User $user, Lead $lead): bool
    {
        return $user->can(Permission::LeadsManage->value);
    }
}
