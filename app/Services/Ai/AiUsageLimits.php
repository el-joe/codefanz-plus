<?php

namespace App\Services\Ai;

use App\Enums\AiFeature;
use App\Enums\AiUnavailableReason;
use App\Models\AiUsageLog;
use App\Services\Settings;
use Illuminate\Support\Facades\DB;

/**
 * Soft caps below the global monthly budget (Settings → AI limits): requests per staff member per day, tokens per
 * staff member per month and tokens per feature per month. A blank setting means "no cap"; 0 blocks the call.
 * Failed calls count as requests too, so a broken prompt cannot be retried endlessly.
 */
class AiUsageLimits
{
    public const GROUP = 'ai';

    public function __construct(private Settings $settings) {}

    public function exceededReason(AiFeature $feature, ?int $userId = null): ?AiUnavailableReason
    {
        $featureLimit = $this->featureMonthlyTokenLimit($feature);

        if ($featureLimit !== null && $this->featureTokensThisMonth($feature) >= $featureLimit) {
            return AiUnavailableReason::FeatureLimitReached;
        }

        if ($userId === null) {
            return null;
        }

        $requestLimit = $this->userDailyRequestLimit();

        if ($requestLimit !== null && $this->userRequestsToday($userId) >= $requestLimit) {
            return AiUnavailableReason::UserLimitReached;
        }

        $tokenLimit = $this->userMonthlyTokenLimit();

        if ($tokenLimit !== null && $this->userTokensThisMonth($userId) >= $tokenLimit) {
            return AiUnavailableReason::UserLimitReached;
        }

        return null;
    }

    public function userDailyRequestLimit(): ?int
    {
        return $this->limit('user_daily_requests');
    }

    public function userMonthlyTokenLimit(): ?int
    {
        return $this->limit('user_monthly_tokens');
    }

    public function featureMonthlyTokenLimit(AiFeature $feature): ?int
    {
        return $this->limit($feature->value.'_monthly_tokens');
    }

    public function userRequestsToday(int $userId): int
    {
        return AiUsageLog::query()->where('user_id', $userId)->where('created_at', '>=', now()->startOfDay())->count();
    }

    public function userTokensThisMonth(int $userId): int
    {
        return (int) AiUsageLog::query()->where('user_id', $userId)->inMonth(now())->sum(DB::raw('input_tokens + output_tokens'));
    }

    public function featureTokensThisMonth(AiFeature $feature): int
    {
        return (int) AiUsageLog::query()->where('feature', $feature)->inMonth(now())->sum(DB::raw('input_tokens + output_tokens'));
    }

    private function limit(string $key): ?int
    {
        $value = $this->settings->get(self::GROUP, $key);

        return is_numeric($value) ? max(0, (int) $value) : null;
    }
}
