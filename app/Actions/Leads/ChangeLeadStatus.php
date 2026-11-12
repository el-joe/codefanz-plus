<?php

namespace App\Actions\Leads;

use App\Enums\LeadActivityType;
use App\Enums\LeadStatus;
use App\Events\LeadStatusChanged;
use App\Exceptions\LeadVersionConflict;
use App\Models\Lead;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Moves a lead through the pipeline (table, detail page and Kanban). Re-validates the transition,
 * requires a reason for Lost, honours lock_version, logs the timeline, audits and fires LeadStatusChanged.
 */
class ChangeLeadStatus
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @throws LeadVersionConflict when the lead changed since $expectedVersion
     * @throws ValidationException when the transition is not allowed
     */
    public function handle(Lead $lead, LeadStatus $to, ?string $lostReason, int $expectedVersion, User $actor): Lead
    {
        [$lead, $from] = DB::transaction(function () use ($lead, $to, $lostReason, $expectedVersion, $actor): array {
            $lead = Lead::query()->lockForUpdate()->findOrFail($lead->getKey());

            if ($lead->lock_version !== $expectedVersion) {
                throw new LeadVersionConflict($lead->lock_version);
            }

            $from = $lead->status;

            if (! $from->canTransitionTo($to)) {
                throw ValidationException::withMessages([
                    'status' => __('A lead cannot move from :from to :to.', ['from' => $from->label(), 'to' => $to->label()]),
                ]);
            }

            $lostReason = $to === LeadStatus::Lost ? trim((string) $lostReason) : null;

            if ($to === LeadStatus::Lost && $lostReason === '') {
                throw ValidationException::withMessages(['lost_reason' => __('Tell the team why this lead was lost.')]);
            }

            $lead->forceFill([
                'status' => $to,
                'lost_reason' => $lostReason,
                'updated_by' => $actor->getKey(),
                'lock_version' => $lead->lock_version + 1,
            ])->save();

            $lead->activities()->create([
                'type' => LeadActivityType::StatusChange,
                'body' => $lostReason,
                'meta' => ['from' => $from->value, 'to' => $to->value],
                'user_id' => $actor->getKey(),
            ]);

            $this->audit->record(
                operation: 'lead.status_changed',
                auditable: $lead,
                user: $actor,
                before: ['status' => $from->value],
                after: ['status' => $to->value, 'lost_reason' => $lostReason, 'version' => $lead->lock_version],
            );

            return [$lead, $from];
        });

        LeadStatusChanged::dispatch($lead, $from, $to, $actor);

        return $lead;
    }
}
