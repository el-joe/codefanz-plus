<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\RefundStatus;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;

/**
 * Four-eyes refunds: staff who create payment links (or finance) request; only a payments.refund-approve holder
 * who is not the requester approves or rejects; finance records manual refunds. AI agents and MCP clients have
 * no refund path at all.
 */
class RefundPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::PaymentsCreateLinks->value) || $user->can(Permission::PaymentsRefundApprove->value);
    }

    public function create(User $user, Payment $payment): bool
    {
        return $this->viewAny($user) && $payment->status->isCollected();
    }

    public function approve(User $user, Refund $refund): bool
    {
        return $user->can(Permission::PaymentsRefundApprove->value)
            && $refund->status === RefundStatus::Requested
            && $refund->requested_by !== $user->getKey();
    }

    public function reject(User $user, Refund $refund): bool
    {
        return $this->approve($user, $refund);
    }

    public function complete(User $user, Refund $refund): bool
    {
        return $user->can(Permission::PaymentsRefundApprove->value) && $refund->status === RefundStatus::ManualRequired;
    }
}
