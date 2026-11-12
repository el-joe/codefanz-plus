<?php

namespace App\Actions\Support;

use App\Enums\ActorType;
use App\Enums\TicketStatus;
use App\Models\SupportTicket;
use App\Services\AuditLogger;

/**
 * Closes tickets that stayed resolved for `codefanz.support.auto_close_after_days` without a new reply.
 */
class CloseResolvedTickets
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @return int number of tickets closed
     */
    public function handle(): int
    {
        $days = max(1, (int) config('codefanz.support.auto_close_after_days'));
        $closed = 0;

        SupportTicket::query()
            ->where('status', TicketStatus::Resolved)
            ->where('resolved_at', '<=', now()->subDays($days))
            ->chunkById(100, function ($tickets) use (&$closed, $days): void {
                foreach ($tickets as $ticket) {
                    $ticket->forceFill(['status' => TicketStatus::Closed, 'closed_at' => now()])->save();
                    $this->audit->record(
                        operation: 'ticket.auto_closed',
                        auditable: $ticket,
                        actorType: ActorType::System,
                        actorLabel: 'tickets:close-resolved',
                        before: ['status' => TicketStatus::Resolved->value],
                        after: ['status' => TicketStatus::Closed->value, 'after_days' => $days],
                    );
                    $closed++;
                }
            });

        return $closed;
    }
}
