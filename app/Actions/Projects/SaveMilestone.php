<?php

namespace App\Actions\Projects;

use App\Models\Milestone;
use App\Models\Project;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates or updates a milestone and its dependencies. Dependencies must belong to the same project and may not
 * form a cycle. Status changes go through ChangeMilestoneStatus; paid_minor is never written here.
 */
class SaveMilestone
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<int>  $dependencyIds
     *
     * @throws ValidationException
     */
    public function handle(Project $project, ?Milestone $milestone, array $data, array $dependencyIds, User $actor, ?int $expectedVersion = null): Milestone
    {
        return DB::transaction(function () use ($project, $milestone, $data, $dependencyIds, $actor, $expectedVersion): Milestone {
            $attributes = Arr::only($data, ['title', 'description', 'due_date', 'amount_minor', 'currency']);
            $isNew = $milestone === null;
            $before = null;

            if ($isNew) {
                $milestone = new Milestone([
                    ...$attributes,
                    'project_id' => $project->getKey(),
                    'sort_order' => (int) Milestone::query()->where('project_id', $project->getKey())->max('sort_order') + 1,
                ]);
                $milestone->save();
            } else {
                $milestone = Milestone::query()->lockForUpdate()->findOrFail($milestone->getKey());

                if ($expectedVersion !== null && $milestone->lock_version !== $expectedVersion) {
                    throw ValidationException::withMessages(['lock_version' => __('Someone else changed this milestone while you were editing. Reload to see their changes.')]);
                }

                $before = $this->snapshot($milestone);
                $this->guardBilling($milestone, $attributes);
                $milestone->fill($attributes);
                $milestone->lock_version++;
                $milestone->save();
            }

            $dependencyIds = array_values(array_unique(array_map('intval', $dependencyIds)));
            $this->guardDependencies($project, $milestone, $dependencyIds);
            $milestone->dependencies()->sync($dependencyIds);

            $this->audit->record(
                operation: $isNew ? 'milestone.created' : 'milestone.updated',
                auditable: $milestone,
                user: $actor,
                before: $before,
                after: $this->snapshot($milestone) + ['project' => $project->reference],
            );

            return $milestone;
        });
    }

    /**
     * Once a milestone has payment links its currency is fixed, and its amount cannot drop below what was collected.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws ValidationException
     */
    private function guardBilling(Milestone $milestone, array $attributes): void
    {
        if (isset($attributes['currency']) && $attributes['currency'] !== $milestone->currency && $milestone->paymentLinks()->exists()) {
            throw ValidationException::withMessages(['currency' => __('The currency cannot change after payment links were created for this milestone.')]);
        }

        if (isset($attributes['amount_minor']) && (int) $attributes['amount_minor'] < $milestone->paid_minor) {
            throw ValidationException::withMessages(['amount' => __('The amount cannot be lower than what was already paid.')]);
        }
    }

    /**
     * @param  list<int>  $dependencyIds
     *
     * @throws ValidationException
     */
    private function guardDependencies(Project $project, Milestone $milestone, array $dependencyIds): void
    {
        if ($dependencyIds === []) {
            return;
        }

        if (in_array($milestone->getKey(), $dependencyIds, true)) {
            throw ValidationException::withMessages(['dependencies' => __('A milestone cannot depend on itself.')]);
        }

        $sameProject = Milestone::query()->where('project_id', $project->getKey())->whereKey($dependencyIds)->count();

        if ($sameProject !== count($dependencyIds)) {
            throw ValidationException::withMessages(['dependencies' => __('Dependencies must be milestones of the same project.')]);
        }

        if ($this->wouldCreateCycle($project, $milestone->getKey(), $dependencyIds)) {
            throw ValidationException::withMessages(['dependencies' => __('These dependencies would create a loop: a milestone would end up waiting for itself.')]);
        }
    }

    /**
     * Walk the existing graph from the proposed dependencies; reaching the milestone itself means a cycle.
     *
     * @param  list<int>  $dependencyIds
     */
    public function wouldCreateCycle(Project $project, int $milestoneId, array $dependencyIds): bool
    {
        /** @var array<int, list<int>> $graph milestone id => ids it depends on */
        $graph = DB::table('milestone_dependencies')
            ->join('milestones', 'milestones.id', '=', 'milestone_dependencies.milestone_id')
            ->where('milestones.project_id', $project->getKey())
            ->where('milestone_dependencies.milestone_id', '!=', $milestoneId)
            ->get(['milestone_dependencies.milestone_id', 'milestone_dependencies.depends_on_id'])
            ->groupBy('milestone_id')
            ->map(fn ($rows) => $rows->pluck('depends_on_id')->map(fn ($id): int => (int) $id)->all())
            ->all();

        $stack = $dependencyIds;
        $seen = [];

        while ($stack !== []) {
            $current = array_pop($stack);

            if ($current === $milestoneId) {
                return true;
            }

            if (isset($seen[$current])) {
                continue;
            }

            $seen[$current] = true;
            array_push($stack, ...($graph[$current] ?? []));
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Milestone $milestone): array
    {
        return [
            'title' => $milestone->title,
            'due_date' => $milestone->due_date?->toDateString(),
            'amount_minor' => $milestone->amount_minor,
            'currency' => $milestone->currency,
            'status' => $milestone->status->value,
            'dependencies' => $milestone->dependencies()->pluck('milestones.id')->all(),
            'version' => $milestone->lock_version,
        ];
    }
}
