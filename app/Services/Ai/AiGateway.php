<?php

namespace App\Services\Ai;

use App\Contracts\AiProvider;
use App\Enums\AiFeature;
use App\Enums\AiProviderName;
use App\Enums\AiUnavailableReason;
use App\Enums\AiUsageStatus;
use App\Exceptions\AiProviderException;
use App\Exceptions\AiUnavailable;
use App\Models\AiUsageLog;
use App\Services\KillSwitches;
use App\Services\Settings;
use Illuminate\Support\Facades\Log;

/**
 * The single entry point for AI calls (assistant now, Content Studio and Radar later). It picks the provider
 * from Settings, enforces the feature's kill switch and the monthly budget (hard stop), and writes one
 * ai_usage_logs row per provider call. Prompts and responses are never logged.
 */
class AiGateway
{
    public function __construct(
        private Settings $settings,
        private KillSwitches $killSwitches,
        private AiBudget $budget,
    ) {}

    public function providerName(): AiProviderName
    {
        $configured = $this->settings->get('integrations', 'ai_provider');

        return AiProviderName::tryFrom(is_string($configured) ? $configured : '')
            ?? AiProviderName::tryFrom((string) config('codefanz.ai.default_provider'))
            ?? AiProviderName::OpenAi;
    }

    public function provider(): AiProvider
    {
        $name = $this->providerName();
        $key = $this->settings->get('integrations', $name->value.'_api_key');

        if (! is_string($key) || $key === '') {
            return new NullAiProvider($name->value);
        }

        $model = $this->settings->get('integrations', $name->value.'_model');
        $model = is_string($model) && $model !== '' ? $model : (string) config('codefanz.ai.default_models.'.$name->value);

        return match ($name) {
            AiProviderName::OpenAi => new OpenAiProvider($key, $model),
            AiProviderName::Anthropic => new AnthropicProvider($key, $model),
        };
    }

    /**
     * Why the feature cannot call AI right now, or null when it can.
     */
    public function unavailableReason(AiFeature $feature): ?AiUnavailableReason
    {
        return match (true) {
            $this->killSwitches->isActive($feature->killSwitch()) => AiUnavailableReason::Disabled,
            ! $this->provider()->isConfigured() => AiUnavailableReason::NotConfigured,
            $this->budget->isExceeded() => AiUnavailableReason::BudgetExceeded,
            default => null,
        };
    }

    /**
     * @param  array{user_id?: int|null, lead_id?: int|null, chat_session_id?: int|null}  $refs
     *
     * @throws AiUnavailable
     */
    public function complete(AiFeature $feature, AiRequest $request, array $refs = []): AiResponse
    {
        $reason = $this->unavailableReason($feature);

        if ($reason !== null) {
            throw new AiUnavailable($reason);
        }

        $provider = $this->provider();
        $startedAt = hrtime(true);

        try {
            $response = $provider->chat($request);
        } catch (AiProviderException $exception) {
            $this->log($feature, $provider->name(), $provider->model(), AiUsageStatus::Failed, $startedAt, $refs, error: $exception->getMessage());

            Log::warning('AI provider call failed.', [
                'feature' => $feature->value,
                'provider' => $provider->name(),
                'model' => $provider->model(),
                'error' => $exception->getMessage(),
            ]);

            throw new AiUnavailable(AiUnavailableReason::Failed, $exception);
        }

        $this->log($feature, $provider->name(), $response->model, AiUsageStatus::Succeeded, $startedAt, $refs, $response->inputTokens, $response->outputTokens);

        return $response;
    }

    /**
     * @param  array{user_id?: int|null, lead_id?: int|null, chat_session_id?: int|null}  $refs
     */
    private function log(
        AiFeature $feature,
        string $provider,
        string $model,
        AiUsageStatus $status,
        int|float $startedAt,
        array $refs,
        int $inputTokens = 0,
        int $outputTokens = 0,
        ?string $error = null,
    ): void {
        AiUsageLog::create([
            'feature' => $feature,
            'provider' => $provider,
            'model' => mb_substr($model, 0, 100),
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'estimated_cost_minor' => $this->budget->estimateCostMinor($model, $inputTokens, $outputTokens),
            'currency' => 'USD',
            'latency_ms' => (int) round((hrtime(true) - $startedAt) / 1_000_000),
            'status' => $status,
            'error' => $error !== null ? mb_substr($error, 0, 500) : null,
            'user_id' => $refs['user_id'] ?? null,
            'lead_id' => $refs['lead_id'] ?? null,
            'chat_session_id' => $refs['chat_session_id'] ?? null,
        ]);
    }
}
