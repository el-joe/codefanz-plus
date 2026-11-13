<?php

use App\Enums\Role;
use App\Models\User;
use App\Services\Operations\ProductionReadiness;
use App\Services\Operations\SchedulerHeartbeat;
use App\Services\Settings;

/**
 * A configuration that passes every critical check.
 */
function configureProductionLikeEnvironment(object $test): void
{
    app()->detectEnvironment(fn () => 'production');
    config([
        'app.debug' => false,
        'app.key' => 'base64:'.base64_encode(str_repeat('k', 32)),
        'app.url' => 'https://codefanz.example',
        'session.secure' => true,
        'session.encrypt' => true,
        'mail.default' => 'smtp',
        'mail.mailers.smtp.host' => 'smtp.mail.example',
        'mail.from.address' => 'hello@codefanz.example',
        'queue.default' => 'database',
        'queue.connections.database.retry_after' => 1900,
        'cache.default' => 'database',
        'codefanz.security.allow_private_outbound' => false,
    ]);
    app(Settings::class)->set('seo', 'block_indexing', 'allow');
    app(SchedulerHeartbeat::class)->beat();
    User::factory()->role(Role::SuperAdmin)->withConfirmedTwoFactor()->create();
}

function readinessStatus(string $check): string
{
    return collect(app(ProductionReadiness::class)->checks())->firstWhere('check', $check)['status'];
}

test('a correctly configured production environment passes', function () {
    configureProductionLikeEnvironment($this);

    $this->artisan('app:check-production')
        ->expectsOutputToContain('Production checks passed')
        ->assertSuccessful();
});

test('each critical misconfiguration fails the command', function (string $check, Closure $breakIt) {
    configureProductionLikeEnvironment($this);
    $breakIt($this);

    expect(readinessStatus($check))->toBe(ProductionReadiness::FAIL);
    $this->artisan('app:check-production')
        ->expectsOutputToContain('critical production setting(s) are wrong')
        ->assertFailed();
})->with([
    'debug on' => ['Debug mode', fn () => config(['app.debug' => true])],
    'not production' => ['Environment', fn ($test) => app()->detectEnvironment(fn () => 'staging')],
    'http url' => ['HTTPS URL', fn () => config(['app.url' => 'http://codefanz.example'])],
    'missing key' => ['Application key', fn () => config(['app.key' => ''])],
    'insecure cookie' => ['Secure cookies', fn () => config(['session.secure' => false])],
    'log mailer' => ['Mail', fn () => config(['mail.default' => 'log'])],
    'example sender' => ['Mail', fn () => config(['mail.from.address' => 'hello@example.com'])],
    'missing smtp host' => ['Mail', fn () => config(['mail.mailers.smtp.host' => null])],
    'sync queue' => ['Queue', fn () => config(['queue.default' => 'sync'])],
    'short retry window' => ['Queue retry window', fn () => config(['queue.connections.database.retry_after' => 90])],
    'array cache' => ['Cache store', fn () => config(['cache.default' => 'array'])],
    'indexing blocked' => ['Search indexing', fn () => app(Settings::class)->set('seo', 'block_indexing', 'block')],
    'stale scheduler' => ['Scheduler', fn ($test) => $test->travel(10)->minutes()],
    'no super admin' => ['Super Admin', fn () => User::query()->delete()],
    'private outbound allowed' => ['Private outbound', fn () => config(['codefanz.security.allow_private_outbound' => true])],
]);

test('a missing storage link fails', function () {
    configureProductionLikeEnvironment($this);
    app()->usePublicPath(storage_path('framework/testing/public-without-link'));

    expect(readinessStatus('Storage link'))->toBe(ProductionReadiness::FAIL);
});

test('a scheduler that never ran fails', function () {
    configureProductionLikeEnvironment($this);
    cache()->forget(config('codefanz.operations.scheduler_heartbeat.cache_key'));

    expect(readinessStatus('Scheduler'))->toBe(ProductionReadiness::FAIL);
});

test('recommendations are warnings and do not fail the command', function () {
    configureProductionLikeEnvironment($this);
    config(['codefanz.security.csp.report_only' => true, 'codefanz.operations.health.token' => null, 'session.encrypt' => false]);

    expect(readinessStatus('Content-Security-Policy'))->toBe(ProductionReadiness::WARN)
        ->and(readinessStatus('Health details'))->toBe(ProductionReadiness::WARN)
        ->and(readinessStatus('Session encryption'))->toBe(ProductionReadiness::WARN);

    $this->artisan('app:check-production')->expectsOutputToContain('warning(s)')->assertSuccessful();
});

test('the retry window is compared with the longest job timeout', function () {
    expect(app(ProductionReadiness::class)->longestJobTimeout())->toBe(1800);
});

test('the output never prints secret values', function () {
    configureProductionLikeEnvironment($this);
    config(['mail.mailers.smtp.password' => 'smtp-secret-value', 'codefanz.operations.health.token' => 'health-secret-value']);

    $this->artisan('app:check-production')
        ->doesntExpectOutputToContain('smtp-secret-value')
        ->doesntExpectOutputToContain('health-secret-value')
        ->doesntExpectOutputToContain((string) config('app.key'))
        ->assertSuccessful();
});
