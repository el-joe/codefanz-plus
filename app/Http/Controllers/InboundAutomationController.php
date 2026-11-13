<?php

namespace App\Http\Controllers;

use App\Actions\Automation\RunInboundAutomation;
use App\Enums\AutomationDirection;
use App\Enums\AutomationRunStatus;
use App\Enums\KillSwitchKey;
use App\Models\AutomationRun;
use App\Models\AutomationWorkflow;
use App\Services\Automation\WebhookSigner;
use App\Services\KillSwitches;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * POST /webhooks/n8n/{key}: an inbound n8n request. Order of checks: endpoint exists and is enabled, body size,
 * fresh timestamp, HMAC signature over "{timestamp}.{raw body}" with the endpoint's secret, then the delivery id
 * must be new (replay protection, unique per endpoint). Only then is the endpoint's single whitelisted action run.
 * Requests with a bad signature are not stored.
 */
class InboundAutomationController extends Controller
{
    public function __invoke(Request $request, string $key, WebhookSigner $signer, KillSwitches $killSwitches, RunInboundAutomation $run): JsonResponse
    {
        $workflow = AutomationWorkflow::query()
            ->where('key', $key)
            ->where('direction', AutomationDirection::Inbound)
            ->with('actingUser')
            ->first();

        if ($workflow === null) {
            return $this->error('not_found', 404);
        }

        if ($killSwitches->isActive(KillSwitchKey::AutomationWebhooks)) {
            return $this->error('paused', 503);
        }

        if (! $workflow->is_enabled) {
            return $this->error('disabled', 403);
        }

        $body = $request->getContent();

        if (strlen($body) > (int) config('codefanz.automation.max_body_bytes', 262144)) {
            return $this->error('too_large', 413);
        }

        $timestamp = (string) $request->header(WebhookSigner::HEADER_TIMESTAMP, '');
        $signature = (string) $request->header(WebhookSigner::HEADER_SIGNATURE, '');
        $delivery = trim((string) $request->header(WebhookSigner::HEADER_DELIVERY, ''));

        if (! $signer->isFresh($timestamp) || ! $signer->verify((string) $workflow->secret, $timestamp, $body, $signature)) {
            return $this->error('invalid_signature', 401);
        }

        if ($delivery === '' || strlen($delivery) > 100 || ! preg_match('/^[A-Za-z0-9._:-]+$/', $delivery)) {
            return $this->error('missing_delivery_id', 400);
        }

        $input = json_decode($body, true);

        if (! is_array($input)) {
            return $this->error('invalid_json', 400);
        }

        try {
            $record = $workflow->runs()->create([
                'uuid' => (string) Str::uuid(),
                'direction' => AutomationDirection::Inbound,
                'event' => (string) $workflow->action?->value,
                'idempotency_key' => $delivery,
                'payload_hash' => hash('sha256', $body),
                'payload' => null,
                'status' => AutomationRunStatus::Pending,
                'attempts' => 1,
                'ip_address' => $request->ip(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return $this->error('replayed', 409);
        }

        $startedAt = hrtime(true);

        try {
            $result = $run->handle($workflow, $input);
        } catch (ValidationException $exception) {
            return $this->finish($record, $workflow, AutomationRunStatus::Rejected, 422, ['error' => 'validation_failed', 'errors' => $exception->errors()], $startedAt);
        } catch (AuthorizationException $exception) {
            return $this->finish($record, $workflow, AutomationRunStatus::Rejected, 403, ['error' => 'forbidden', 'message' => $exception->getMessage()], $startedAt);
        } catch (Throwable $exception) {
            report($exception);

            return $this->finish($record, $workflow, AutomationRunStatus::Failed, 500, ['error' => 'server_error'], $startedAt);
        }

        return $this->finish($record, $workflow, AutomationRunStatus::Succeeded, 200, ['status' => 'ok', 'result' => $result], $startedAt);
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function finish(AutomationRun $run, AutomationWorkflow $workflow, AutomationRunStatus $status, int $httpStatus, array $response, int|float $startedAt): JsonResponse
    {
        $json = (string) json_encode($response, JSON_UNESCAPED_UNICODE);

        $run->forceFill([
            'status' => $status,
            'http_status' => $httpStatus,
            'response' => mb_substr($json, 0, 1000),
            'error' => $status === AutomationRunStatus::Succeeded ? null : mb_substr((string) ($response['error'] ?? ''), 0, 500),
            'duration_ms' => (int) round((hrtime(true) - $startedAt) / 1_000_000),
            'completed_at' => now(),
        ])->save();

        $workflow->forceFill(['last_run_at' => now(), 'last_status' => $status])->save();

        return new JsonResponse($response, $httpStatus);
    }

    private function error(string $code, int $status): JsonResponse
    {
        return new JsonResponse(['error' => $code], $status);
    }
}
