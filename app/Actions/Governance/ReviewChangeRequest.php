<?php

namespace App\Actions\Governance;

use App\Enums\ChangeRequestStatus;
use App\Exceptions\GovernanceException;
use App\Models\ChangeRequest;
use App\Models\User;
use App\Services\AuditLogger;

class ReviewChangeRequest
{
    public function __construct(private AuditLogger $audit) {}

    public function approve(ChangeRequest $changeRequest, User $reviewer, ?string $note = null): ChangeRequest
    {
        return $this->decide($changeRequest, $reviewer, ChangeRequestStatus::Approved, $note);
    }

    public function reject(ChangeRequest $changeRequest, User $reviewer, ?string $note = null): ChangeRequest
    {
        return $this->decide($changeRequest, $reviewer, ChangeRequestStatus::Rejected, $note);
    }

    public function requestChanges(ChangeRequest $changeRequest, User $reviewer, string $note): ChangeRequest
    {
        return $this->decide($changeRequest, $reviewer, ChangeRequestStatus::ChangesRequested, $note);
    }

    private function decide(ChangeRequest $changeRequest, User $reviewer, ChangeRequestStatus $decision, ?string $note): ChangeRequest
    {
        if (! $changeRequest->status->isReviewable()) {
            throw GovernanceException::notReviewable($changeRequest->status->value);
        }

        if ($changeRequest->requested_by !== null && $changeRequest->requested_by === $reviewer->getKey()) {
            throw GovernanceException::selfReview();
        }

        if (! $reviewer->can($changeRequest->required_permission->value)) {
            throw GovernanceException::missingPermission($changeRequest->required_permission->value);
        }

        $changeRequest->forceFill([
            'status' => $decision,
            'reviewed_by' => $reviewer->getKey(),
            'reviewed_at' => now(),
            'review_note' => $note,
        ])->save();

        $this->audit->record(
            operation: 'change_request.'.$decision->value,
            auditable: $changeRequest,
            user: $reviewer,
            after: ['status' => $decision->value, 'note' => $note],
            changeRequestId: $changeRequest->id,
        );

        return $changeRequest;
    }
}
