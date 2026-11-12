<?php

namespace App\Actions\Refunds;

use App\Enums\RefundStatus;
use App\Models\Refund;
use App\Models\User;
use App\Notifications\RefundStatusChanged;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Step 2: a finance user who did not request the refund approves or rejects it. Approval hands the refund to
 * ProcessRefund straight away; the four-eyes rule is enforced here as well as in RefundPolicy.
 */
class DecideRefund
{
    public function __construct(private AuditLogger $audit, private ProcessRefund $processRefund) {}

    /**
     * @throws AuthorizationException|ValidationException
     */
    public function approve(Refund $refund, User $approver, ?string $note = null): Refund
    {
        $refund = $this->decide($refund, $approver, RefundStatus::Approved, $note);

        return $this->processRefund->handle($refund, $approver);
    }

    /**
     * @throws AuthorizationException|ValidationException
     */
    public function reject(Refund $refund, User $approver, string $note): Refund
    {
        $refund = $this->decide($refund, $approver, RefundStatus::Rejected, $note);

        if ($refund->requester !== null) {
            $refund->requester->notify(new RefundStatusChanged($refund, forClient: false));
        }

        return $refund;
    }

    private function decide(Refund $refund, User $approver, RefundStatus $decision, ?string $note): Refund
    {
        return DB::transaction(function () use ($refund, $approver, $decision, $note): Refund {
            $locked = Refund::query()->lockForUpdate()->findOrFail($refund->getKey());

            if ($locked->requested_by === $approver->getKey()) {
                throw new AuthorizationException(__('A refund must be approved by someone other than the requester.'));
            }

            if ($locked->status !== RefundStatus::Requested) {
                throw ValidationException::withMessages(['refund' => __('This refund was already decided.')]);
            }

            $locked->forceFill([
                'status' => $decision,
                'decided_by' => $approver->getKey(),
                'decided_at' => now(),
                'decision_note' => $note,
            ])->save();

            $this->audit->record(operation: 'refund.'.$decision->value, auditable: $locked, user: $approver, before: ['status' => RefundStatus::Requested->value], after: [
                'status' => $decision->value, 'reference' => $locked->reference, 'amount_minor' => $locked->amount_minor, 'currency' => $locked->currency, 'note' => $note,
            ]);

            return $locked->load(['payment', 'requester']);
        });
    }
}
