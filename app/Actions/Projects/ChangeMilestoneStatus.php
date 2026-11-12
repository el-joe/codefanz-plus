<?php

namespace App\Actions\Projects;

use App\Enums\MilestoneStatus;
use App\Models\Milestone;
use App\Models\User;
use App\Notifications\MilestoneAwaitingApproval;
use App\Services\AuditLogger;
use App\Services\DeliveryRecipients;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Staff moves a milestone through its workflow. Starting requires every dependency to be completed; sending for
 * approval notifies the customer's portal users. Approval itself is the client's (ReviewMilestone).
 */
class ChangeMilestoneStatus
{
    public function __construct(private AuditLogger $audit, private DeliveryRecipients $recipients) {}

    /**
     * @throws ValidationException
     */
    public function handle(Milestone $milestone, MilestoneStatus $to, User $actor): Milestone
    {
        [$milestone, $from] = DB::transaction(function () use ($milestone, $to, $actor): array {
            $milestone = Milestone::query()->with('dependencies:id,title,status')->lockForUpdate()->findOrFail($milestone->getKey());
            $from = $milestone->status;

            if (! $from->staffCanMoveTo($to)) {
                throw ValidationException::withMessages(['status' => __('A milestone cannot move from :from to :to.', ['from' => $from->label(), 'to' => $to->label()])]);
            }

            if ($to === MilestoneStatus::InProgress && $from === MilestoneStatus::Pending) {
                $blocking = $milestone->blockingDependencies();

                if ($blocking->isNotEmpty()) {
                    throw ValidationException::withMessages(['status' => __('This milestone can start once these are completed: :titles.', ['titles' => $blocking->pluck('title')->implode(', ')])]);
                }
            }

            $milestone->status = $to;
            $milestone->lock_version++;

            match ($to) {
                MilestoneStatus::InProgress => $milestone->started_at ??= now(),
                MilestoneStatus::AwaitingApproval => $milestone->submitted_at = now(),
                MilestoneStatus::Completed => $milestone->completed_at = now(),
                default => null,
            };

            if ($to === MilestoneStatus::Pending) {
                $milestone->started_at = null;
            }

            if ($from === MilestoneStatus::Completed) {
                $milestone->completed_at = null;
            }

            $milestone->save();

            $this->audit->record(
                operation: 'milestone.status_changed',
                auditable: $milestone,
                user: $actor,
                before: ['status' => $from->value],
                after: ['status' => $to->value, 'version' => $milestone->lock_version],
            );

            return [$milestone, $from];
        });

        if ($to === MilestoneStatus::AwaitingApproval) {
            $milestone->load('project');
            Notification::send($this->recipients->clientUsers($milestone->project->customer_id), new MilestoneAwaitingApproval($milestone));
        }

        return $milestone;
    }
}
