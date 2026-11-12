<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Payment;
use App\Models\User;

/**
 * Finance views: anyone who creates payment links or approves refunds reads the ledger. Nobody edits payments;
 * staff may only ask the gateway to re-verify one.
 */
class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::PaymentsCreateLinks->value) || $user->can(Permission::PaymentsRefundApprove->value);
    }

    public function view(User $user, Payment $payment): bool
    {
        return $this->viewAny($user);
    }

    public function verify(User $user, Payment $payment): bool
    {
        return $this->viewAny($user);
    }
}
