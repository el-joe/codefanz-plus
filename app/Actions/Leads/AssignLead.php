<?php

namespace App\Actions\Leads;

use App\Enums\LeadActivityType;
use App\Exceptions\LeadVersionConflict;
use App\Models\Lead;
use App\Models\User;
use App\Notifications\LeadAssigned;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Assigns (or unassigns) a lead. The new assignee is notified unless they assigned it to themselves.
 */
class AssignLead
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @throws LeadVersionConflict
     */
    public function handle(Lead $lead, ?User $assignee, int $expectedVersion, User $actor): Lead
    {
        [$lead, $changed] = DB::transaction(function () use ($lead, $assignee, $expectedVersion, $actor): array {
            $lead = Lead::query()->lockForUpdate()->findOrFail($lead->getKey());

            if ($lead->lock_version !== $expectedVersion) {
                throw new LeadVersionConflict($lead->lock_version);
            }

            $previous = $lead->assigned_to;

            if ($previous === $assignee?->getKey()) {
                return [$lead, false];
            }

            $lead->forceFill([
                'assigned_to' => $assignee?->getKey(),
                'updated_by' => $actor->getKey(),
                'lock_version' => $lead->lock_version + 1,
            ])->save();

            $lead->activities()->create([
                'type' => LeadActivityType::Assignment,
                'meta' => ['from' => $previous, 'to' => $assignee?->getKey(), 'to_name' => $assignee?->name],
                'user_id' => $actor->getKey(),
            ]);

            $this->audit->record(
                operation: 'lead.assigned',
                auditable: $lead,
                user: $actor,
                before: ['assigned_to' => $previous],
                after: ['assigned_to' => $assignee?->getKey(), 'version' => $lead->lock_version],
            );

            return [$lead, true];
        });

        if ($changed && $assignee && $assignee->isNot($actor)) {
            $assignee->notify(new LeadAssigned($lead, $actor));
        }

        return $lead;
    }
}
