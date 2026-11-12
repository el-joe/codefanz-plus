<?php

namespace App\Events;

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A lead moved through the pipeline. Hook point for the n8n webhook delivery.
 */
class LeadStatusChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Lead $lead,
        public LeadStatus $from,
        public LeadStatus $to,
        public ?User $changedBy = null,
    ) {}
}
