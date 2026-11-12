<?php

namespace App\Services\Social;

use App\Enums\SocialPlatform;
use App\Enums\SocialVariantStatus;
use App\Exceptions\SocialApiException;
use App\Models\SocialAccount;
use App\Models\SocialMetricSnapshot;
use App\Models\SocialPostVariant;
use Illuminate\Support\Collection;

/**
 * Metric snapshots for posts published through the APIs, and the honest per-variant state the dashboard shows:
 * numbers only when the platform reported them; otherwise "not available for this platform", "not available for
 * manual posts", "not collected yet" or the last error.
 */
class SocialMetrics
{
    public const AVAILABLE = 'available';

    public const PENDING = 'pending';

    public const MANUAL = 'manual';

    public const UNSUPPORTED = 'unsupported';

    public const ERROR = 'error';

    public function __construct(private SocialPublisherManager $publishers, private SocialTokenRefresher $tokens) {}

    /**
     * @return array{fetched: int, failed: int}
     */
    public function fetch(): array
    {
        $fetched = 0;
        $failed = 0;

        SocialPostVariant::query()
            ->where('status', SocialVariantStatus::Published)
            ->whereNotNull('external_id')
            ->where('published_at', '>=', now()->subDays((int) config('codefanz.social.metrics_window_days', 90)))
            ->with(['account', 'post'])
            ->orderBy('id')
            ->get()
            ->each(function (SocialPostVariant $variant) use (&$fetched, &$failed): void {
                $publisher = $this->publishers->for($variant->platform);
                $account = $variant->account;

                if ($publisher === null || ! $account instanceof SocialAccount || ! $account->canPublishDirectly()) {
                    $variant->forceFill(['metrics_error' => __('The account is not connected, so metrics cannot be read.'), 'metrics_checked_at' => now()])->save();
                    $failed++;

                    return;
                }

                try {
                    if ($this->tokens->needsRefresh($account) || $account->tokenExpired()) {
                        $account = $this->tokens->refresh($account);
                    }

                    $metrics = $publisher->metrics($variant, $account);
                } catch (SocialApiException $exception) {
                    $variant->forceFill(['metrics_error' => mb_substr($exception->getMessage(), 0, 500), 'metrics_checked_at' => now()])->save();
                    $failed++;

                    return;
                }

                if ($metrics['impressions'] !== null || $metrics['engagements'] !== null || $metrics['clicks'] !== null) {
                    $variant->snapshots()->create([...$metrics, 'captured_at' => now()]);
                }

                $variant->forceFill(['metrics_error' => null, 'metrics_checked_at' => now()])->save();
                $fetched++;
            });

        return ['fetched' => $fetched, 'failed' => $failed];
    }

    public function stateOf(SocialPostVariant $variant): string
    {
        return match (true) {
            $variant->status === SocialVariantStatus::ExportedManually => self::MANUAL,
            ! $variant->platform->supportsMetrics() => self::UNSUPPORTED,
            $variant->latestSnapshot !== null => self::AVAILABLE,
            filled($variant->metrics_error) => self::ERROR,
            default => self::PENDING,
        };
    }

    public static function stateLabel(string $state): string
    {
        return match ($state) {
            self::AVAILABLE => __('Reported by the platform'),
            self::PENDING => __('Not collected yet'),
            self::MANUAL => __('Not available for manual posts'),
            self::UNSUPPORTED => __('Not available for this platform'),
            default => __('Unavailable'),
        };
    }

    /**
     * Dashboard data from the latest snapshot of every published / manually posted variant.
     *
     * @return array{rows: Collection<int, SocialPostVariant>, totals: array{impressions: int|null, engagements: int|null, clicks: int|null}, byPlatform: array<string, array{label: string, impressions: int|null, engagements: int|null, clicks: int|null, posts: int}>, reporting: int}
     */
    public function dashboard(?SocialPlatform $platform = null): array
    {
        $rows = SocialPostVariant::query()
            ->whereIn('status', [SocialVariantStatus::Published, SocialVariantStatus::ExportedManually])
            ->when($platform, fn ($query) => $query->where('platform', $platform))
            ->with(['post', 'account', 'latestSnapshot'])
            ->latest('published_at')
            ->latest('exported_at')
            ->limit(200)
            ->get();

        $sum = function (Collection $snapshots, string $metric): ?int {
            $values = $snapshots->pluck($metric)->filter(fn ($value): bool => $value !== null);

            return $values->isEmpty() ? null : (int) $values->sum();
        };

        $snapshots = $rows->pluck('latestSnapshot')->filter();
        $byPlatform = [];

        foreach ($rows->groupBy(fn (SocialPostVariant $variant): string => $variant->platform->value) as $key => $group) {
            $groupSnapshots = $group->pluck('latestSnapshot')->filter();
            $byPlatform[$key] = [
                'label' => SocialPlatform::from($key)->label(),
                'impressions' => $sum($groupSnapshots, 'impressions'),
                'engagements' => $sum($groupSnapshots, 'engagements'),
                'clicks' => $sum($groupSnapshots, 'clicks'),
                'posts' => $group->count(),
            ];
        }

        return [
            'rows' => $rows,
            'totals' => ['impressions' => $sum($snapshots, 'impressions'), 'engagements' => $sum($snapshots, 'engagements'), 'clicks' => $sum($snapshots, 'clicks')],
            'byPlatform' => $byPlatform,
            'reporting' => $snapshots->count(),
        ];
    }

    /**
     * @return Collection<int, SocialMetricSnapshot>
     */
    public function history(SocialPostVariant $variant): Collection
    {
        return $variant->snapshots()->limit(30)->get();
    }
}
