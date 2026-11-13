<?php

namespace App\Actions\Radar;

use App\Enums\KillSwitchKey;
use App\Exceptions\RadarConnectorException;
use App\Models\RadarPlatform;
use App\Services\KillSwitches;
use App\Services\Radar\RadarPlatforms;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * One collection run for an API platform: respects the global radar kill switch, the per-platform switch, the
 * cadence and the hourly request budget, records the outcome on the platform and feeds the pipeline.
 */
class FetchRadarPlatform
{
    public function __construct(private RadarPlatforms $platforms, private IngestRadarProjects $ingest, private KillSwitches $killSwitches) {}

    /**
     * @return array{status: string, created?: int, updated?: int, unchanged?: int, error?: string}
     */
    public function handle(RadarPlatform $platform, bool $force = false): array
    {
        $connector = $this->platforms->connector($platform);

        return match (true) {
            $this->killSwitches->isActive(KillSwitchKey::RadarCollection) => ['status' => 'paused'],
            ! $platform->is_enabled => ['status' => 'disabled'],
            $connector === null => ['status' => 'not_api'],
            ! $connector->isConfigured() => ['status' => 'needs_credentials'],
            ! $force && ! $platform->isDue() => ['status' => 'not_due'],
            default => $this->run($platform),
        };
    }

    /**
     * @return array{status: string, created?: int, updated?: int, unchanged?: int, error?: string}
     */
    private function run(RadarPlatform $platform): array
    {
        $key = 'radar-fetch:'.$platform->key;

        if (RateLimiter::tooManyAttempts($key, max(1, $platform->rate_limit_per_hour))) {
            return ['status' => 'rate_limited'];
        }

        RateLimiter::hit($key, 3600);
        $platform->forceFill(['last_run_at' => now()])->save();

        try {
            $items = $this->platforms->connector($platform)?->fetch((int) config('codefanz.radar.max_projects_per_fetch', 50)) ?? [];
        } catch (RadarConnectorException $exception) {
            $platform->forceFill(['last_error' => $exception->getMessage(), 'last_run_count' => 0])->save();
            $this->platforms->refreshStatuses();
            Log::warning('Radar fetch failed.', ['platform' => $platform->key, 'error' => $exception->getMessage()]);

            return ['status' => 'error', 'error' => $exception->getMessage()];
        }

        $counts = $this->ingest->handle($platform, $items);

        $platform->forceFill(['last_error' => null, 'last_success_at' => now(), 'last_run_count' => count($items)])->save();
        $this->platforms->refreshStatuses();

        return ['status' => 'ok', ...$counts];
    }
}
