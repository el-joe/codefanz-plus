<?php

namespace App\Actions\Leads;

use App\Enums\LeadActivityType;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;
use App\Services\AuditLogger;

/**
 * Adds a note, call, email or meeting to a lead's timeline. Timeline entries are append-only.
 */
class LogLeadActivity
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(Lead $lead, LeadActivityType $type, string $body, User $actor): LeadActivity
    {
        $activity = $lead->activities()->create([
            'type' => $type,
            'body' => trim($body),
            'user_id' => $actor->getKey(),
        ]);

        $lead->touch();

        // The note itself may hold personal data; the audit entry records only that one was added.
        $this->audit->record(
            operation: 'lead.activity_logged',
            auditable: $lead,
            user: $actor,
            after: ['activity_id' => $activity->id, 'type' => $type->value],
        );

        return $activity;
    }
}
