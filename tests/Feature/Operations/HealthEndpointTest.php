<?php

use App\Providers\AppServiceProvider;
use App\Services\Operations\SchedulerHeartbeat;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    config([
        'codefanz.operations.health.token' => 'health-token-for-tests-0123456789',
        'codefanz.operations.health.allowed_ips' => [],
        'codefanz.operations.health.min_free_disk_mb' => 0,
        'queue.default' => 'database',
    ]);
});

test('a healthy application answers 200 with only the overall status for anonymous callers', function () {
    app(SchedulerHeartbeat::class)->beat();

    $this->getJson('/health')
        ->assertOk()
        ->assertExactJson(['status' => 'ok'])
        ->assertHeader('Cache-Control', 'no-store, private');
});

test('the token reveals every check without exposing configuration', function () {
    app(SchedulerHeartbeat::class)->beat();

    $response = $this->getJson('/health', ['X-Health-Token' => 'health-token-for-tests-0123456789'])
        ->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('checks.database.ok', true)
        ->assertJsonPath('checks.cache.ok', true)
        ->assertJsonPath('checks.queue.ok', true)
        ->assertJsonPath('checks.queue.driver', 'database')
        ->assertJsonPath('checks.queue.queues.media.oldest_waiting_seconds', 0)
        ->assertJsonPath('checks.scheduler.ok', true)
        ->assertJsonPath('checks.disk.ok', true)
        ->assertJsonStructure(['checked_at', 'checks' => ['database' => ['latency_ms'], 'disk' => ['free_mb'], 'queue' => ['failed_last_24h']]]);

    expect($response->getContent())
        ->not->toContain((string) config('database.connections.mysql.database'))
        ->not->toContain((string) config('database.connections.mysql.host'))
        ->not->toContain((string) config('app.key'));
});

test('a wrong token shows no details', function () {
    app(SchedulerHeartbeat::class)->beat();

    $this->getJson('/health', ['X-Health-Token' => 'wrong'])->assertOk()->assertExactJson(['status' => 'ok']);
});

test('an allow-listed network sees the details', function () {
    config(['codefanz.operations.health.allowed_ips' => ['10.0.0.0/8']]);
    app(SchedulerHeartbeat::class)->beat();

    $this->withServerVariables(['REMOTE_ADDR' => '10.20.30.40'])->getJson('/health')->assertOk()->assertJsonStructure(['checks']);
    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])->getJson('/health')->assertOk()->assertJsonMissingPath('checks');
});

test('a scheduler that has not run makes the application unhealthy', function () {
    $this->getJson('/health')->assertStatus(503)->assertExactJson(['status' => 'fail']);

    $this->getJson('/health', ['X-Health-Token' => 'health-token-for-tests-0123456789'])
        ->assertStatus(503)
        ->assertJsonPath('checks.scheduler.ok', false)
        ->assertJsonPath('checks.scheduler.last_run_seconds_ago', null)
        ->assertJsonPath('checks.database.ok', true);
});

test('a stale heartbeat is unhealthy', function () {
    $this->travelTo(now()->subMinutes(10), fn () => app(SchedulerHeartbeat::class)->beat());

    $this->getJson('/health', ['X-Health-Token' => 'health-token-for-tests-0123456789'])
        ->assertStatus(503)
        ->assertJsonPath('checks.scheduler.ok', false);
});

test('a job waiting longer than the threshold marks the queue unhealthy', function () {
    app(SchedulerHeartbeat::class)->beat();
    DB::table('jobs')->insert([
        'queue' => 'media', 'payload' => '{}', 'attempts' => 0, 'reserved_at' => null,
        'available_at' => now()->subHour()->getTimestamp(), 'created_at' => now()->subHour()->getTimestamp(),
    ]);

    $this->getJson('/health', ['X-Health-Token' => 'health-token-for-tests-0123456789'])
        ->assertStatus(503)
        ->assertJsonPath('checks.queue.ok', false)
        ->assertJsonPath('checks.queue.queues.default.oldest_waiting_seconds', 0)
        ->assertJsonPath('checks.queue.queues.media.oldest_waiting_seconds', fn (int $age): bool => $age >= 3600);
});

test('low disk space is unhealthy', function () {
    config(['codefanz.operations.health.min_free_disk_mb' => PHP_INT_MAX]);
    app(SchedulerHeartbeat::class)->beat();

    $this->getJson('/health', ['X-Health-Token' => 'health-token-for-tests-0123456789'])
        ->assertStatus(503)
        ->assertJsonPath('checks.disk.ok', false);
});

test('the endpoint uses no session', function () {
    app(SchedulerHeartbeat::class)->beat();

    $this->get('/health')->assertOk()->assertCookieMissing(config('session.cookie'));
});

test('TRUSTED_PROXIES makes the forwarded client address visible to the allow-list', function () {
    config(['codefanz.security.trusted_proxies' => ['10.0.0.5'], 'codefanz.operations.health.allowed_ips' => ['203.0.113.0/24']]);
    app()->getProvider(AppServiceProvider::class)->boot();
    app(SchedulerHeartbeat::class)->beat();

    try {
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])
            ->getJson('/health', ['X-Forwarded-For' => '203.0.113.7'])
            ->assertOk()->assertJsonStructure(['checks']);

        // An untrusted sender cannot spoof the header.
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.9'])
            ->getJson('/health', ['X-Forwarded-For' => '203.0.113.7'])
            ->assertOk()->assertJsonMissingPath('checks');
    } finally {
        TrustProxies::flushState();
    }
});
