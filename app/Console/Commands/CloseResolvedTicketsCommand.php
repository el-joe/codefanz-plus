<?php

namespace App\Console\Commands;

use App\Actions\Support\CloseResolvedTickets;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('tickets:close-resolved')]
#[Description('Close support tickets that have stayed resolved for the configured number of days')]
class CloseResolvedTicketsCommand extends Command
{
    public function handle(CloseResolvedTickets $close): int
    {
        $count = $close->handle();
        $this->info("Closed {$count} resolved ticket(s).");

        return self::SUCCESS;
    }
}
