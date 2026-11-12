<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\SupportTicket;
use App\Models\User;

/**
 * Admin ticket queue: support.manage reads, replies, adds internal notes, assigns and changes status.
 * Clients use the portal, where tickets are scoped by customer.
 */
class SupportTicketPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::SupportManage->value);
    }

    public function view(User $user, SupportTicket $ticket): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, SupportTicket $ticket): bool
    {
        return $user->can(Permission::SupportManage->value);
    }
}
