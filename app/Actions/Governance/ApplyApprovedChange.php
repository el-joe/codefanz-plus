<?php

namespace App\Actions\Governance;

use App\Contracts\Approvable;
use App\Enums\ChangeRequestStatus;
use App\Exceptions\GovernanceException;
use App\Models\ChangeRequest;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\KillSwitches;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The only code path that applies AI- or MCP-originated changes to public content.
 * Every precondition is re-verified at execution time.
 */
class ApplyApprovedChange
{
    public function __construct(private KillSwitches $killSwitches, private AuditLogger $audit) {}

    public function handle(ChangeRequest $changeRequest, User $executor): ChangeRequest
    {
        try {
            return DB::transaction(function () use ($changeRequest, $executor): ChangeRequest {
                $changeRequest = ChangeRequest::query()->lockForUpdate()->findOrFail($changeRequest->getKey());
                $this->guard($changeRequest);

                /** @var Model&Approvable $target */
                $target = $changeRequest->target()->lockForUpdate()->firstOrFail();

                if ($target->approvableVersion() !== $changeRequest->target_version) {
                    throw GovernanceException::staleTarget($changeRequest->target_version, $target->approvableVersion());
                }

                $target->applyApprovedAttributes($changeRequest->proposed, $changeRequest->locale);

                $changeRequest->forceFill([
                    'status' => ChangeRequestStatus::Applied,
                    'applied_at' => now(),
                    'apply_result' => ['version' => $target->approvableVersion(), 'applied_by' => $executor->getKey()],
                ])->save();

                $this->audit->record(
                    operation: 'change_request.applied',
                    auditable: $target,
                    user: $executor,
                    before: $changeRequest->before,
                    after: $changeRequest->proposed,
                    changeRequestId: $changeRequest->id,
                );

                return $changeRequest;
            });
        } catch (GovernanceException $exception) {
            $this->markFailed($changeRequest, $executor, $exception);

            throw $exception;
        }
    }

    private function guard(ChangeRequest $changeRequest): void
    {
        if ($changeRequest->status !== ChangeRequestStatus::Approved) {
            throw GovernanceException::notApproved();
        }

        if ($changeRequest->isExpired()) {
            throw GovernanceException::expired();
        }

        $killSwitch = $changeRequest->origin->killSwitch();

        if ($killSwitch !== null && $this->killSwitches->isActive($killSwitch)) {
            throw GovernanceException::killSwitchActive($killSwitch->value);
        }

        $reviewer = $changeRequest->reviewer;

        if ($reviewer === null || ! $reviewer->is_active || ! $reviewer->can($changeRequest->required_permission->value)) {
            throw GovernanceException::missingPermission($changeRequest->required_permission->value);
        }
    }

    private function markFailed(ChangeRequest $changeRequest, User $executor, GovernanceException $exception): void
    {
        $changeRequest->refresh();

        if ($changeRequest->status === ChangeRequestStatus::Approved && ! $exception->retryable) {
            $changeRequest->forceFill([
                'status' => ChangeRequestStatus::Failed,
                'apply_result' => ['error' => $exception->getMessage()],
            ])->save();
        }

        $this->audit->record(
            operation: 'change_request.apply_failed',
            auditable: $changeRequest,
            user: $executor,
            after: ['error' => $exception->getMessage()],
            result: 'failed',
            changeRequestId: $changeRequest->id,
        );
    }
}
