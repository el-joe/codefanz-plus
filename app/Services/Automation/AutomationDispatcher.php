<?php

namespace App\Services\Automation;

use App\Enums\AutomationDirection;
use App\Enums\AutomationEvent;
use App\Enums\AutomationRunStatus;
use App\Jobs\DeliverAutomationWebhook;
use App\Models\AutomationRun;
use App\Models\AutomationWorkflow;
use Closure;
use Illuminate\Support\Str;

/**
 * Fans a domain event out to every enabled outbound workflow subscribed to it: one run (with the exact body that
 * will be signed and resent on retries) and one queued delivery per workflow.
 */
class AutomationDispatcher
{
    /**
     * @param  Closure(AutomationWorkflow): array<string, mixed>  $data  payload per workflow (personal data depends on it)
     */
    public function dispatch(AutomationEvent $event, Closure $data): int
    {
        $workflows = AutomationWorkflow::query()
            ->where('direction', AutomationDirection::Outbound)
            ->where('is_enabled', true)
            ->get()
            ->filter(fn (AutomationWorkflow $workflow): bool => $workflow->subscribesTo($event->value));

        foreach ($workflows as $workflow) {
            $this->queue($workflow, $event, $data($workflow));
        }

        return $workflows->count();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function queue(AutomationWorkflow $workflow, AutomationEvent $event, array $data): AutomationRun
    {
        $uuid = (string) Str::uuid();
        $payload = [
            'id' => $uuid,
            'event' => $event->value,
            'occurred_at' => now()->toIso8601String(),
            'data' => $data,
        ];

        $run = $workflow->runs()->create([
            'uuid' => $uuid,
            'direction' => AutomationDirection::Outbound,
            'event' => $event->value,
            'idempotency_key' => $uuid,
            'payload_hash' => hash('sha256', (string) json_encode($payload)),
            'payload' => $payload,
            'status' => AutomationRunStatus::Pending,
        ]);

        DeliverAutomationWebhook::dispatch($run->id)->afterCommit();

        return $run;
    }
}
