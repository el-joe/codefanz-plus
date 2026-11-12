<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\PaymentLink;
use App\Models\User;

class PaymentLinkPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::PaymentsCreateLinks->value) || $user->can(Permission::PaymentsRefundApprove->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::PaymentsCreateLinks->value);
    }

    public function cancel(User $user, PaymentLink $link): bool
    {
        return $user->can(Permission::PaymentsCreateLinks->value);
    }
}
