<?php

namespace App\Events;

use App\Models\SupportTicket;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A support ticket was opened (portal or n8n). Hook point for n8n.
 */
class SupportTicketOpened
{
    use Dispatchable, SerializesModels;

    public function __construct(public SupportTicket $ticket) {}
}
