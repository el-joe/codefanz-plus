<?php

namespace App\Jobs;

use App\Enums\AutomationRunStatus;
use App\Models\AutomationRun;
use App\Services\Automation\AutomationDelivery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Delivers one outbound n8n run. Attempts are counted on the run itself; a failed attempt is released back to the
 * queue with the configured backoff until the run succeeds or reaches the maximum, then it is marked failed and
 * can be retried from the admin.
 */
class DeliverAutomationWebhook implements ShouldQueue
{
    use Queueable;

    public int $timeout = 60;

    public function __construct(public int $runId) {}

    public function tries(): int
    {
        return (int) config('codefanz.automation.tries', 5) + 1;
    }

    public function handle(AutomationDelivery $delivery): void
    {
        $run = AutomationRun::query()->with('workflow')->find($this->runId);

        if ($run === null || $run->status !== AutomationRunStatus::Pending) {
            return;
        }

        if ($delivery->attempt($run) === AutomationRunStatus::Pending && $this->job !== null) {
            $this->release($delivery->backoffSeconds($run->attempts));
        }
    }
}
