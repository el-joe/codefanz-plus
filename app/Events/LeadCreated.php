<?php

namespace App\Events;

use App\Models\Lead;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A lead entered the CRM (any source). Hook point for the n8n webhook delivery.
 */
class LeadCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public Lead $lead) {}
}
