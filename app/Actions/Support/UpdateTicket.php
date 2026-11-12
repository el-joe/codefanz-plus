<?php

namespace App\Actions\Support;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\SupportTicketUpdated;
use App\Services\AuditLogger;
use App\Services\DeliveryRecipients;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Status, priority and assignee changes. Resolving stamps resolved_at, closing stamps closed_at, reopening clears
 * them. Clients may only resolve their own open ticket (ActorType User, from the portal); staff may do anything
 * except reopen a closed ticket.
 */
class UpdateTicket
{
    public function __construct(private AuditLogger $audit, private DeliveryRecipients $recipients) {}

    /**
     * @throws ValidationException
     */
    public function handle(SupportTicket $ticket, TicketStatus $status, ?TicketPriority $priority, ?int $assigneeId, User $actor, bool $byClient = false): SupportTicket
    {
        [$ticket, $before] = DB::transaction(function () use ($ticket, $status, $priority, $assigneeId, $actor, $byClient): array {
            $ticket = SupportTicket::query()->lockForUpdate()->findOrFail($ticket->getKey());
            $before = ['status' => $ticket->status->value, 'priority' => $ticket->priority->value, 'assigned_to' => $ticket->assigned_to];

            if ($ticket->status === TicketStatus::Closed && $status !== TicketStatus::Closed) {
                throw ValidationException::withMessages(['status' => __('Closed tickets cannot be reopened. Ask the customer to open a new ticket.')]);
            }

            if ($byClient && ($status !== TicketStatus::Resolved || ! $ticket->status->isOpen())) {
                throw ValidationException::withMessages(['status' => __('This ticket cannot be changed any more.')]);
            }

            if ($status !== $ticket->status) {
                match ($status) {
                    TicketStatus::Resolved => $ticket->resolved_at = now(),
                    TicketStatus::Closed => $ticket->closed_at = now(),
                    default => $ticket->resolved_at = null,
                };
                $ticket->status = $status;
                $ticket->last_activity_at = now();
            }

            if (! $byClient) {
                $ticket->priority = $priority ?? $ticket->priority;
                $ticket->assigned_to = $assigneeId;
            }

            $ticket->save();

            $after = ['status' => $ticket->status->value, 'priority' => $ticket->priority->value, 'assigned_to' => $ticket->assigned_to];

            if ($after !== $before) {
                $this->audit->record(operation: 'ticket.updated', auditable: $ticket, user: $actor, before: $before, after: $after);
            }

            return [$ticket, $before];
        });

        if ($before['status'] !== $ticket->status->value) {
            $ticket->loadMissing('assignee');
            $recipients = $byClient
                ? $this->recipients->supportTeam($ticket->assignee, except: $actor)
                : $this->recipients->clientUsers($ticket->customer_id, except: $actor);

            Notification::send($recipients, new SupportTicketUpdated($ticket, 'status', $actor->name, forClient: ! $byClient));
        }

        return $ticket;
    }
}
