<?php

namespace App\Actions\Automation;

use App\Models\AutomationWorkflow;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Radar\RadarPlatforms;
use Illuminate\Support\Str;

/**
 * Creates, updates, rotates the secret of, and deletes n8n workflows. A new secret is generated server-side and
 * returned once (to show it a single time); the audit trail never contains it.
 */
class SaveAutomationWorkflow
{
    public function __construct(private AuditLogger $audit, private RadarPlatforms $radar) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: AutomationWorkflow, 1: string|null} the workflow and, when created, its new secret
     */
    public function handle(?AutomationWorkflow $workflow, array $data, User $user): array
    {
        $isNew = $workflow === null;
        $workflow ??= new AutomationWorkflow(['created_by' => $user->getKey()]);
        $before = $isNew ? null : $this->snapshot($workflow);
        $secret = null;

        $workflow->fill([...$data, 'updated_by' => $user->getKey()]);

        if ($isNew) {
            $workflow->key = $this->uniqueKey((string) $data['name']);
            $workflow->secret = $secret = $this->newSecret();
        }

        $workflow->save();
        $this->radar->refreshStatuses();

        $this->audit->record(operation: $isNew ? 'automation.workflow_created' : 'automation.workflow_updated', auditable: $workflow, user: $user, before: $before, after: $this->snapshot($workflow));

        return [$workflow, $secret];
    }

    public function rotateSecret(AutomationWorkflow $workflow, User $user): string
    {
        $workflow->forceFill(['secret' => $secret = $this->newSecret(), 'updated_by' => $user->getKey()])->save();

        $this->audit->record(operation: 'automation.secret_rotated', auditable: $workflow, user: $user);

        return $secret;
    }

    public function toggle(AutomationWorkflow $workflow, bool $enabled, User $user): void
    {
        $workflow->forceFill(['is_enabled' => $enabled, 'updated_by' => $user->getKey()])->save();
        $this->radar->refreshStatuses();

        $this->audit->record(operation: $enabled ? 'automation.workflow_enabled' : 'automation.workflow_disabled', auditable: $workflow, user: $user);
    }

    public function delete(AutomationWorkflow $workflow, User $user): void
    {
        $workflow->delete();
        $this->radar->refreshStatuses();

        $this->audit->record(operation: 'automation.workflow_deleted', auditable: $workflow, user: $user, before: $this->snapshot($workflow));
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(AutomationWorkflow $workflow): array
    {
        return [
            'key' => $workflow->key,
            'name' => $workflow->name,
            'direction' => $workflow->direction?->value,
            'url' => $workflow->url,
            'events' => $workflow->events,
            'action' => $workflow->action?->value,
            'acts_as_user_id' => $workflow->acts_as_user_id,
            'include_personal_data' => $workflow->include_personal_data,
            'is_enabled' => $workflow->is_enabled,
        ];
    }

    private function newSecret(): string
    {
        return 'whsec_'.Str::random(48);
    }

    private function uniqueKey(string $name): string
    {
        $base = Str::limit(Str::slug($name) ?: 'workflow', 40, '');

        do {
            $key = $base.'-'.Str::lower(Str::random(8));
        } while (AutomationWorkflow::withTrashed()->where('key', $key)->exists());

        return $key;
    }
}
