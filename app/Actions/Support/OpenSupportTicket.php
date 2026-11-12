<?php

namespace App\Actions\Support;

use App\Enums\TicketStatus;
use App\Models\Customer;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\SupportTicketUpdated;
use App\Services\AuditLogger;
use App\Services\DeliveryRecipients;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * A client opens a ticket from the portal; the first message becomes the first reply. Support staff are notified.
 */
class OpenSupportTicket
{
    public function __construct(private AuditLogger $audit, private DeliveryRecipients $recipients) {}

    /**
     * @param  array{subject: string, category: string, priority: string, project_id?: int|null, body: string}  $data
     */
    public function handle(Customer $customer, array $data, User $client): SupportTicket
    {
        $ticket = DB::transaction(function () use ($customer, $data, $client): SupportTicket {
            $ticket = SupportTicket::create([
                ...Arr::only($data, ['subject', 'category', 'priority', 'project_id']),
                'customer_id' => $customer->getKey(),
                'opened_by' => $client->getKey(),
                'status' => TicketStatus::Open,
                'last_activity_at' => now(),
            ]);

            $ticket->replies()->create([
                'user_id' => $client->getKey(),
                'from_client' => true,
                'body' => trim($data['body']),
            ]);

            $this->audit->record(
                operation: 'ticket.opened',
                auditable: $ticket,
                user: $client,
                after: ['reference' => $ticket->reference, 'category' => $ticket->category->value, 'priority' => $ticket->priority->value, 'project_id' => $ticket->project_id],
            );

            return $ticket;
        });

        Notification::send($this->recipients->supportTeam(null), new SupportTicketUpdated($ticket, 'opened', $client->name, forClient: false));

        return $ticket;
    }
}
