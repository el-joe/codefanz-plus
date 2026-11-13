<?php

namespace App\Services\Automation;

use App\Enums\AutomationRunStatus;
use App\Enums\KillSwitchKey;
use App\Models\AutomationRun;
use App\Services\KillSwitches;
use App\Services\Security\OutboundUrlGuard;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * One delivery attempt of an outbound run: signed POST of the stored body, outcome recorded on the run. A run stays
 * pending until it succeeds or uses up its attempts; the job schedules the retries with backoff.
 */
class AutomationDelivery
{
    public function __construct(private WebhookSigner $signer, private KillSwitches $killSwitches, private OutboundUrlGuard $guard) {}

    public function attempt(AutomationRun $run): AutomationRunStatus
    {
        $workflow = $run->workflow;

        if ($workflow === null || $workflow->trashed() || ! $workflow->is_enabled || $this->killSwitches->isActive(KillSwitchKey::AutomationWebhooks)) {
            return $this->finish($run, AutomationRunStatus::Failed, error: $this->killSwitches->isActive(KillSwitchKey::AutomationWebhooks)
                ? 'Paused by an emergency control.'
                : 'Workflow disabled or deleted.');
        }

        // SSRF: the stored URL must still resolve to a public address; redirects are never followed.
        if ($this->guard->reason((string) $workflow->url) !== null) {
            $run->attempts++;

            return $this->finish($run, AutomationRunStatus::Failed, error: 'Blocked: the webhook URL does not resolve to a public address.');
        }

        $body = (string) json_encode($run->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $timestamp = now()->getTimestamp();
        $startedAt = hrtime(true);
        $run->attempts++;

        try {
            $response = Http::timeout((int) config('codefanz.automation.timeout_seconds', 10))
                ->withHeaders([
                    WebhookSigner::HEADER_EVENT => $run->event,
                    WebhookSigner::HEADER_DELIVERY => $run->uuid,
                    WebhookSigner::HEADER_TIMESTAMP => (string) $timestamp,
                    WebhookSigner::HEADER_SIGNATURE => $this->signer->sign((string) $workflow->secret, $timestamp, $body),
                    'User-Agent' => 'CodeFanz-Webhooks/1.0',
                ])
                ->withOptions($this->guard->requestOptions((string) $workflow->url))
                ->withBody($body, 'application/json')
                ->post((string) $workflow->url);
        } catch (ConnectionException) {
            return $this->failedAttempt($run, null, 'Connection failed.', $startedAt);
        }

        if ($response->successful()) {
            return $this->finish($run, AutomationRunStatus::Succeeded, $response->status(), mb_substr($response->body(), 0, 1000), null, $startedAt);
        }

        return $this->failedAttempt($run, $response->status(), 'HTTP '.$response->status(), $startedAt, mb_substr($response->body(), 0, 1000));
    }

    public function maxAttempts(): int
    {
        return max(1, (int) config('codefanz.automation.tries', 5));
    }

    public function backoffSeconds(int $attempts): int
    {
        $backoff = (array) config('codefanz.automation.backoff', [60]);

        return (int) ($backoff[min(max(0, $attempts - 1), count($backoff) - 1)] ?? 60);
    }

    private function failedAttempt(AutomationRun $run, ?int $status, string $error, int|float $startedAt, ?string $response = null): AutomationRunStatus
    {
        $final = $run->attempts >= $this->maxAttempts();

        return $this->finish($run, $final ? AutomationRunStatus::Failed : AutomationRunStatus::Pending, $status, $response, $error, $startedAt);
    }

    private function finish(AutomationRun $run, AutomationRunStatus $status, ?int $httpStatus = null, ?string $response = null, ?string $error = null, int|float|null $startedAt = null): AutomationRunStatus
    {
        $run->forceFill([
            'status' => $status,
            'http_status' => $httpStatus,
            'response' => $response,
            'error' => $error,
            'duration_ms' => $startedAt !== null ? (int) round((hrtime(true) - $startedAt) / 1_000_000) : null,
            'completed_at' => $status === AutomationRunStatus::Pending ? null : now(),
        ])->save();

        $run->workflow?->forceFill(['last_run_at' => now(), 'last_status' => $status])->save();

        return $status;
    }
}
