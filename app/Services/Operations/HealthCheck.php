<?php

namespace App\Services\Operations;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Throwable;

/**
 * Runtime health for `/health`: database, cache, queue backlog, scheduler heartbeat and free disk space.
 * Every check is guarded so one failing dependency never hides the others. Results carry no secrets
 * (no hosts, credentials or exception messages), only states, ages and sizes.
 */
class HealthCheck
{
    public function __construct(private SchedulerHeartbeat $heartbeat) {}

    /**
     * @return array{ok: bool, checks: array<string, array<string, mixed>>}
     */
    public function run(): array
    {
        $checks = [
            'database' => $this->guard(fn (): array => $this->database()),
            'cache' => $this->guard(fn (): array => $this->cache()),
            'queue' => $this->guard(fn (): array => $this->queue()),
            'scheduler' => $this->guard(fn (): array => $this->scheduler()),
            'disk' => $this->guard(fn (): array => $this->disk()),
        ];

        return [
            'ok' => collect($checks)->every(fn (array $check): bool => $check['ok']),
            'checks' => $checks,
        ];
    }

    /**
     * @param  callable(): array<string, mixed>  $check
     * @return array<string, mixed>
     */
    private function guard(callable $check): array
    {
        try {
            return $check();
        } catch (Throwable $exception) {
            report($exception);

            return ['ok' => false, 'error' => class_basename($exception)];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function database(): array
    {
        $started = microtime(true);
        DB::select('select 1');

        return ['ok' => true, 'latency_ms' => (int) round((microtime(true) - $started) * 1000)];
    }

    /**
     * @return array<string, mixed>
     */
    private function cache(): array
    {
        $key = 'operations:health-probe:'.Str::random(12);
        Cache::put($key, 'ok', 30);
        $ok = Cache::get($key) === 'ok';
        Cache::forget($key);

        return ['ok' => $ok, 'store' => (string) config('cache.default')];
    }

    /**
     * Oldest waiting job per queue (database driver) or queue sizes (other drivers), plus recent failures.
     *
     * @return array<string, mixed>
     */
    private function queue(): array
    {
        $connection = (string) config('queue.default');
        $driver = (string) config("queue.connections.{$connection}.driver");
        $queues = (array) config('codefanz.operations.queues', ['default']);
        $maxAge = (int) config('codefanz.operations.health.max_queue_age_seconds');
        $result = ['ok' => true, 'driver' => $driver, 'queues' => []];

        foreach ($queues as $queue) {
            if ($driver === 'database') {
                $oldest = DB::connection(config("queue.connections.{$connection}.connection"))
                    ->table((string) config("queue.connections.{$connection}.table", 'jobs'))
                    ->where('queue', $queue)
                    ->whereNull('reserved_at')
                    ->where('available_at', '<=', now()->getTimestamp())
                    ->min('available_at');
                $age = $oldest === null ? 0 : max(0, now()->getTimestamp() - (int) $oldest);
                $result['queues'][$queue] = ['oldest_waiting_seconds' => $age];
                $result['ok'] = $result['ok'] && $age <= $maxAge;
            } elseif ($driver !== 'sync' && $driver !== 'null') {
                $result['queues'][$queue] = ['size' => Queue::connection($connection)->size($queue)];
            }
        }

        $result['failed_last_24h'] = $this->recentFailures();

        return $result;
    }

    private function recentFailures(): ?int
    {
        $table = config('queue.failed.table');

        if (! is_string($table) || ! str_starts_with((string) config('queue.failed.driver'), 'database')) {
            return null;
        }

        return DB::connection(config('queue.failed.database'))->table($table)->where('failed_at', '>=', now()->subDay())->count();
    }

    /**
     * @return array<string, mixed>
     */
    private function scheduler(): array
    {
        return ['ok' => $this->heartbeat->isFresh(), 'last_run_seconds_ago' => $this->heartbeat->ageInSeconds()];
    }

    /**
     * @return array<string, mixed>
     */
    private function disk(): array
    {
        $free = @disk_free_space(storage_path());

        if ($free === false) {
            return ['ok' => false, 'error' => 'unavailable'];
        }

        $freeMb = (int) floor($free / 1024 / 1024);

        return ['ok' => $freeMb >= (int) config('codefanz.operations.health.min_free_disk_mb'), 'free_mb' => $freeMb];
    }
}
