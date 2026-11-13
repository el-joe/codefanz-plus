<?php

namespace App\Services\Operations;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * The scheduler records a timestamp every minute (routes/console.php). `/health` and `app:check-production` read it
 * to tell whether cron is actually running `schedule:run` on this server.
 */
class SchedulerHeartbeat
{
    public function beat(): void
    {
        Cache::forever($this->key(), now()->getTimestamp());
    }

    public function lastBeatAt(): ?CarbonImmutable
    {
        $timestamp = Cache::get($this->key());

        return is_numeric($timestamp) ? CarbonImmutable::createFromTimestamp((int) $timestamp) : null;
    }

    /**
     * Seconds since the last beat, or null when the scheduler has never run.
     */
    public function ageInSeconds(): ?int
    {
        $last = $this->lastBeatAt();

        return $last === null ? null : max(0, (int) $last->diffInSeconds(now(), true));
    }

    public function isFresh(): bool
    {
        $age = $this->ageInSeconds();

        return $age !== null && $age <= (int) config('codefanz.operations.scheduler_heartbeat.max_age_seconds');
    }

    private function key(): string
    {
        return (string) config('codefanz.operations.scheduler_heartbeat.cache_key');
    }
}
