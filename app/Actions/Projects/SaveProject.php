<?php

namespace App\Actions\Projects;

use App\Models\Project;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates or updates a project and its team. Updates honour lock_version (optimistic locking); a new project
 * gets its general conversation thread. Every write is audited.
 */
class SaveProject
{
    private const COLUMNS = [
        'customer_id', 'lead_id', 'name', 'description', 'type', 'status', 'start_date', 'due_date',
        'project_manager_id', 'budget_minor', 'currency', 'show_budget_to_client', 'show_team_to_client',
    ];

    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string|null>  $members  user id => role label
     *
     * @throws ValidationException when the project changed since $expectedVersion
     */
    public function handle(?Project $project, array $data, array $members, User $actor, ?int $expectedVersion = null): Project
    {
        return DB::transaction(function () use ($project, $data, $members, $actor, $expectedVersion): Project {
            $attributes = Arr::only($data, self::COLUMNS);
            $isNew = $project === null;
            $before = null;

            if ($isNew) {
                $project = new Project([...$attributes, 'created_by' => $actor->getKey(), 'updated_by' => $actor->getKey()]);
                $project->save();
                $project->conversations()->create(['customer_id' => $project->customer_id, 'created_by' => $actor->getKey()]);
            } else {
                $project = Project::query()->lockForUpdate()->findOrFail($project->getKey());

                if ($expectedVersion !== null && $project->lock_version !== $expectedVersion) {
                    throw ValidationException::withMessages(['lock_version' => __('Someone else changed this project while you were editing. Reload to see their changes.')]);
                }

                // A project never moves to another customer: its documents, threads and tickets belong to that customer.
                unset($attributes['customer_id']);
                $before = $this->snapshot($project);
                $project->fill([...$attributes, 'updated_by' => $actor->getKey()]);
                $project->lock_version++;
                $project->save();
            }

            $project->members()->sync(collect($members)->mapWithKeys(fn (?string $role, int $userId): array => [$userId => ['role' => filled($role) ? trim($role) : null]])->all());

            $this->audit->record(
                operation: $isNew ? 'project.created' : 'project.updated',
                auditable: $project,
                user: $actor,
                before: $before,
                after: $this->snapshot($project),
            );

            return $project;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Project $project): array
    {
        return [
            'reference' => $project->reference,
            'customer_id' => $project->customer_id,
            'name' => $project->name,
            'status' => $project->status->value,
            'start_date' => $project->start_date?->toDateString(),
            'due_date' => $project->due_date?->toDateString(),
            'project_manager_id' => $project->project_manager_id,
            'budget_minor' => $project->budget_minor,
            'currency' => $project->currency,
            'show_budget_to_client' => $project->show_budget_to_client,
            'show_team_to_client' => $project->show_team_to_client,
            'members' => $project->members()->pluck('users.id')->all(),
            'version' => $project->lock_version,
        ];
    }
}
