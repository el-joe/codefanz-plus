<?php

namespace App\Actions\Support;

use App\Enums\TicketStatus;
use App\Models\SupportTicket;
use App\Models\TicketReply;
use App\Models\User;
use App\Notifications\SupportTicketUpdated;
use App\Services\AuditLogger;
use App\Services\DeliveryRecipients;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Adds a reply and moves the ticket along:
 * - staff public reply → awaiting customer, and stamps first_response_at the first time;
 * - staff internal note → no status change, no SLA stamp, nobody outside the team is told;
 * - client reply → awaiting support (reopens a resolved ticket). Closed tickets take no replies.
 */
class ReplyToTicket
{
    public function __construct(private AuditLogger $audit, private DeliveryRecipients $recipients) {}

    /**
     * @throws ValidationException when the ticket is closed
     */
    public function handle(SupportTicket $ticket, string $body, User $author, bool $fromClient, bool $internal = false): TicketReply
    {
        $internal = $internal && ! $fromClient;

        [$ticket, $reply, $from] = DB::transaction(function () use ($ticket, $body, $author, $fromClient, $internal): array {
            $ticket = SupportTicket::query()->lockForUpdate()->findOrFail($ticket->getKey());

            if ($ticket->status === TicketStatus::Closed) {
                throw ValidationException::withMessages(['body' => __('This ticket is closed. Please open a new ticket.')]);
            }

            $from = $ticket->status;
            $reply = $ticket->replies()->create([
                'user_id' => $author->getKey(),
                'from_client' => $fromClient,
                'body' => trim($body),
                'is_internal' => $internal,
            ]);

            if (! $internal) {
                if ($fromClient) {
                    $ticket->status = TicketStatus::AwaitingSupport;
                    $ticket->resolved_at = null;
                } else {
                    $ticket->status = TicketStatus::AwaitingCustomer;
                    $ticket->first_response_at ??= now();
                }

                $ticket->last_activity_at = now();
                $ticket->save();
            }

            $this->audit->record(
                operation: $internal ? 'ticket.internal_note_added' : 'ticket.replied',
                auditable: $ticket,
                user: $author,
                before: ['status' => $from->value],
                after: ['status' => $ticket->status->value, 'reply_id' => $reply->getKey(), 'from_client' => $fromClient, 'internal' => $internal],
            );

            return [$ticket, $reply, $from];
        });

        if (! $internal) {
            $ticket->loadMissing('assignee');
            $recipients = $fromClient
                ? $this->recipients->supportTeam($ticket->assignee, except: $author)
                : $this->recipients->clientUsers($ticket->customer_id, except: $author);

            Notification::send($recipients, new SupportTicketUpdated($ticket, 'replied', $author->name, forClient: ! $fromClient));
        }

        return $reply;
    }
}
