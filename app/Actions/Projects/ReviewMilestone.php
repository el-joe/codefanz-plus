<?php

namespace App\Actions\Projects;

use App\Enums\MilestoneStatus;
use App\Models\Milestone;
use App\Models\User;
use App\Notifications\MilestoneReviewed;
use App\Services\AuditLogger;
use App\Services\DeliveryRecipients;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * The client approves a milestone awaiting approval, or requests changes (with feedback), from the portal.
 * Requesting changes sends it back to in progress. Audited and the project team is notified.
 */
class ReviewMilestone
{
    public function __construct(private AuditLogger $audit, private DeliveryRecipients $recipients) {}

    /**
     * @throws ValidationException when the milestone is not awaiting approval any more
     */
    public function handle(Milestone $milestone, bool $approve, ?string $feedback, User $client): Milestone
    {
        $milestone = DB::transaction(function () use ($milestone, $approve, $feedback, $client): Milestone {
            $milestone = Milestone::query()->lockForUpdate()->findOrFail($milestone->getKey());

            if ($milestone->status !== MilestoneStatus::AwaitingApproval) {
                throw ValidationException::withMessages(['milestone' => __('This milestone is not waiting for your approval any more.')]);
            }

            if ($approve) {
                $milestone->forceFill([
                    'status' => MilestoneStatus::Approved,
                    'approved_at' => now(),
                    'approved_by' => $client->getKey(),
                    'client_feedback' => null,
                ]);
            } else {
                $milestone->forceFill([
                    'status' => MilestoneStatus::InProgress,
                    'client_feedback' => trim((string) $feedback),
                ]);
            }

            $milestone->lock_version++;
            $milestone->save();

            $this->audit->record(
                operation: $approve ? 'milestone.approved' : 'milestone.changes_requested',
                auditable: $milestone,
                user: $client,
                before: ['status' => MilestoneStatus::AwaitingApproval->value],
                after: ['status' => $milestone->status->value, 'feedback' => $approve ? null : $milestone->client_feedback],
            );

            return $milestone;
        });

        $milestone->load('project');
        Notification::send(
            $this->recipients->projectTeam($milestone->project),
            new MilestoneReviewed($milestone, $approve, $client->name),
        );

        return $milestone;
    }
}
