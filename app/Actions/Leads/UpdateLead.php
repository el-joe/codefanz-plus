<?php

namespace App\Actions\Leads;

use App\Exceptions\LeadVersionConflict;
use App\Models\Lead;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Staff edit of a lead's details (contact, requirements, priority, follow-up, value). Status and
 * assignment have their own actions so they are validated, logged and notified separately.
 */
class UpdateLead
{
    /**
     * @var list<string>
     */
    public const FIELDS = [
        'name', 'email', 'phone', 'company', 'country', 'preferred_locale', 'preferred_contact', 'platforms',
        'description', 'features', 'goals', 'existing_url', 'budget_range', 'timeline', 'deadline', 'priority',
        'follow_up_at', 'estimated_value_minor', 'currency',
    ];

    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws LeadVersionConflict
     */
    public function handle(Lead $lead, array $data, int $expectedVersion, User $actor): Lead
    {
        return DB::transaction(function () use ($lead, $data, $expectedVersion, $actor): Lead {
            $lead = Lead::query()->lockForUpdate()->findOrFail($lead->getKey());

            if ($lead->lock_version !== $expectedVersion) {
                throw new LeadVersionConflict($lead->lock_version);
            }

            $lead->fill([...Arr::only($data, self::FIELDS), 'updated_by' => $actor->getKey()]);
            $lead->email = mb_strtolower(trim($lead->email));
            $changed = array_keys(Arr::except($lead->getDirty(), ['updated_by']));
            $lead->lock_version++;
            $lead->save();

            $lead->services()->sync(array_map('intval', (array) ($data['services'] ?? [])));
            $lead->technologies()->sync(array_map('intval', (array) ($data['technologies'] ?? [])));

            $this->audit->record(
                operation: 'lead.updated',
                auditable: $lead,
                user: $actor,
                after: ['changed' => $changed, 'version' => $lead->lock_version],
            );

            return $lead;
        });
    }
}
