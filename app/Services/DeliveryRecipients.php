<?php

namespace App\Services;

use App\Enums\Permission;
use App\Models\Customer;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Who hears about project and support activity: the customer's active portal users on one side, the project
 * team (or the people who manage projects / support when nobody is assigned) on the other.
 */
class DeliveryRecipients
{
    /**
     * Active portal users with an active membership in the customer.
     *
     * @return Collection<int, User>
     */
    public function clientUsers(Customer|int $customer, ?User $except = null): Collection
    {
        $customerId = $customer instanceof Customer ? $customer->getKey() : $customer;

        return User::query()
            ->where('is_active', true)
            ->whereHas('customers', fn ($query) => $query->whereKey($customerId)->whereNull('customer_user.revoked_at'))
            ->when($except, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->get();
    }

    /**
     * Project manager and team members; everyone with projects.manage when the project has no team yet.
     *
     * @return Collection<int, User>
     */
    public function projectTeam(Project $project, ?User $except = null): Collection
    {
        $team = User::query()
            ->where('is_active', true)
            ->where(fn ($query) => $query
                ->whereKey($project->project_manager_id ?? 0)
                ->orWhereHas('projects', fn ($query) => $query->whereKey($project->getKey())))
            ->get();

        if ($team->isEmpty()) {
            $team = $this->holding(Permission::ProjectsManage);
        }

        return $except ? $team->reject(fn (User $user): bool => $user->is($except))->values() : $team;
    }

    /**
     * The ticket assignee, or everyone with support.manage while it is unassigned.
     *
     * @return Collection<int, User>
     */
    public function supportTeam(?User $assignee, ?User $except = null): Collection
    {
        $team = $assignee !== null && $assignee->is_active ? new Collection([$assignee]) : $this->holding(Permission::SupportManage);

        return $except ? $team->reject(fn (User $user): bool => $user->is($except))->values() : $team;
    }

    /**
     * @return Collection<int, User>
     */
    private function holding(Permission $permission): Collection
    {
        return User::query()->holdingPermission($permission)->get();
    }
}
