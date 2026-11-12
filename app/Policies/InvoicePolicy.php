<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Invoice;
use App\Models\User;

/**
 * Finance views read invoices; people who create payment links also draft, issue and void them.
 * Portal access is scoped by customer in the portal controllers instead.
 */
class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::PaymentsCreateLinks->value) || $user->can(Permission::PaymentsRefundApprove->value);
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::PaymentsCreateLinks->value);
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $user->can(Permission::PaymentsCreateLinks->value);
    }
}
