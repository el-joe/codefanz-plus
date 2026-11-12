<?php

namespace App\Services\Ai;

use App\Models\AiUsageLog;
use App\Services\Settings;
use Illuminate\Support\Carbon;

/**
 * Estimated AI spend against the monthly budget (Settings → Integrations, US dollars). Amounts are US cents.
 */
class AiBudget
{
    public function __construct(private Settings $settings) {}

    /**
     * Estimated cost in US cents from the configured price table (USD per million tokens).
     */
    public function estimateCostMinor(string $model, int $inputTokens, int $outputTokens): float
    {
        $prices = config('codefanz.ai.prices');
        $price = $prices[$model] ?? $this->priceByPrefix($prices, $model) ?? $prices['default'];

        return round((($inputTokens * $price['input']) + ($outputTokens * $price['output'])) / 1_000_000 * 100, 4);
    }

    public function limitMinor(): float
    {
        $value = $this->settings->get('integrations', 'ai_monthly_budget');

        return round((is_numeric($value) ? (float) $value : (float) config('codefanz.ai.default_monthly_budget_usd')) * 100, 4);
    }

    public function spentMinor(?Carbon $month = null): float
    {
        return round((float) AiUsageLog::query()->inMonth($month ?? now())->sum('estimated_cost_minor'), 4);
    }

    public function isExceeded(): bool
    {
        return $this->spentMinor() >= $this->limitMinor();
    }

    /**
     * Dated model ids (e.g. "gpt-4.1-mini-2025-04-14") use the price of their base model.
     *
     * @param  array<string, array{input: float, output: float}>  $prices
     * @return array{input: float, output: float}|null
     */
    private function priceByPrefix(array $prices, string $model): ?array
    {
        $match = collect(array_keys($prices))
            ->filter(fn (string $key): bool => $key !== 'default' && str_starts_with($model, $key.'-'))
            ->sortByDesc(fn (string $key): int => strlen($key))
            ->first();

        return $match !== null ? $prices[$match] : null;
    }
}
