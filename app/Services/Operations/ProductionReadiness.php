<?php

namespace App\Services\Operations;

use App\Enums\Role;
use App\Models\User;
use App\Services\Seo\IndexingPolicy;
use Illuminate\Support\Facades\File;
use ReflectionClass;
use Throwable;

/**
 * Go-live checks behind `php artisan app:check-production`. A `fail` blocks go-live (the command exits non-zero),
 * a `warn` is a recommendation. Messages name settings, never their values.
 */
class ProductionReadiness
{
    public const PASS = 'pass';

    public const WARN = 'warn';

    public const FAIL = 'fail';

    public function __construct(private SchedulerHeartbeat $heartbeat, private IndexingPolicy $indexing) {}

    /**
     * @return list<array{check: string, status: string, message: string}>
     */
    public function checks(): array
    {
        $results = [];

        foreach ($this->definitions() as $name => $check) {
            try {
                [$status, $message] = $check();
            } catch (Throwable $exception) {
                [$status, $message] = [self::FAIL, 'Check could not run: '.class_basename($exception)];
            }

            $results[] = ['check' => $name, 'status' => $status, 'message' => $message];
        }

        return $results;
    }

    /**
     * @return array<string, callable(): array{0: string, 1: string}>
     */
    private function definitions(): array
    {
        return [
            'Environment' => fn (): array => app()->environment('production')
                ? [self::PASS, 'APP_ENV=production']
                : [self::FAIL, 'APP_ENV must be "production" (is "'.app()->environment().'")'],
            'Debug mode' => fn (): array => config('app.debug')
                ? [self::FAIL, 'APP_DEBUG must be false: debug pages expose configuration and stack traces']
                : [self::PASS, 'APP_DEBUG=false'],
            'Application key' => fn (): array => filled(config('app.key'))
                ? [self::PASS, 'APP_KEY is set']
                : [self::FAIL, 'APP_KEY is missing: run php artisan key:generate once and back up .env'],
            'HTTPS URL' => fn (): array => str_starts_with((string) config('app.url'), 'https://')
                ? [self::PASS, 'APP_URL uses https']
                : [self::FAIL, 'APP_URL must start with https:// (links, signed URLs, OAuth callbacks, sitemaps)'],
            'Secure cookies' => fn (): array => config('session.secure') === true || (config('session.secure') === null && str_starts_with((string) config('app.url'), 'https://'))
                ? [self::PASS, 'Session cookie is https-only']
                : [self::FAIL, 'SESSION_SECURE_COOKIE must be true'],
            'Session encryption' => fn (): array => config('session.encrypt')
                ? [self::PASS, 'SESSION_ENCRYPT=true']
                : [self::WARN, 'SESSION_ENCRYPT=true is recommended'],
            'Mail' => fn (): array => $this->mail(),
            'Queue' => fn (): array => $this->queue(),
            'Queue retry window' => fn (): array => $this->retryWindow(),
            'Cache store' => fn (): array => in_array(config('cache.default'), ['array', 'null'], true)
                ? [self::FAIL, 'CACHE_STORE must be persistent (redis, database or file); "'.config('cache.default').'" loses rate limits and the scheduler heartbeat']
                : [self::PASS, 'CACHE_STORE='.config('cache.default')],
            'Storage link' => fn (): array => is_link(public_path('storage')) || File::isDirectory(public_path('storage'))
                ? [self::PASS, 'public/storage exists']
                : [self::FAIL, 'public/storage is missing: run php artisan storage:link (media variants would 404)'],
            'Search indexing' => fn (): array => $this->indexing->blocksIndexing()
                ? [self::FAIL, 'Search engines are blocked (Admin > Settings > SEO > Search engine indexing): robots.txt disallows everything']
                : [self::PASS, 'Indexing allowed'],
            'Scheduler' => fn (): array => $this->scheduler(),
            'Super Admin' => fn (): array => User::query()->role(Role::SuperAdmin->value)->where('is_active', true)->exists()
                ? [self::PASS, 'An active Super Admin exists']
                : [self::FAIL, 'No active Super Admin: run php artisan app:create-super-admin'],
            'Private outbound' => fn (): array => config('codefanz.security.allow_private_outbound')
                ? [self::FAIL, 'ALLOW_PRIVATE_OUTBOUND must be false (SSRF guard)']
                : [self::PASS, 'SSRF guard on'],
            'Content-Security-Policy' => fn (): array => match (true) {
                ! config('codefanz.security.csp.enabled') => [self::WARN, 'CSP_ENABLED=false: enable the policy'],
                (bool) config('codefanz.security.csp.report_only') => [self::WARN, 'CSP is report-only: switch CSP_REPORT_ONLY=false once the console is clean'],
                default => [self::PASS, 'CSP enforced'],
            },
            'Breached passwords' => fn (): array => config('codefanz.security.check_breached_passwords')
                ? [self::PASS, 'CHECK_BREACHED_PASSWORDS=true']
                : [self::WARN, 'CHECK_BREACHED_PASSWORDS=true is recommended'],
            'Log level' => fn (): array => config('logging.channels.'.config('logging.default').'.level', config('logging.channels.daily.level')) === 'debug'
                ? [self::WARN, 'LOG_LEVEL=debug is verbose in production; use warning or error']
                : [self::PASS, 'Log level is not debug'],
            'Health details' => fn (): array => filled(config('codefanz.operations.health.token'))
                ? [self::PASS, 'HEALTH_TOKEN is set']
                : [self::WARN, 'Set HEALTH_TOKEN so monitors can read /health details'],
            'AVIF support' => fn (): array => class_exists(\Imagick::class) && \Imagick::queryFormats('AVIF') !== []
                ? [self::PASS, 'Imagick encodes AVIF']
                : [self::WARN, 'Imagick without AVIF: media variants fall back to WebP only'],
            'Cached config' => fn (): array => app()->configurationIsCached() && app()->routesAreCached()
                ? [self::PASS, 'Config and routes are cached']
                : [self::WARN, 'Run php artisan optimize after every deploy'],
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function mail(): array
    {
        $mailer = (string) config('mail.default');
        $from = (string) config('mail.from.address');

        if (in_array($mailer, ['log', 'array', ''], true)) {
            return [self::FAIL, 'MAIL_MAILER is "'.$mailer.'": invitations, password resets and notifications would not be delivered'];
        }

        if ($from === '' || str_ends_with($from, '@example.com')) {
            return [self::FAIL, 'MAIL_FROM_ADDRESS must be a real sender on your domain'];
        }

        if ($mailer === 'smtp' && blank(config('mail.mailers.smtp.host'))) {
            return [self::FAIL, 'MAIL_HOST is missing'];
        }

        return [self::PASS, 'MAIL_MAILER='.$mailer];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function queue(): array
    {
        $connection = (string) config('queue.default');

        return in_array($connection, ['sync', 'null', ''], true) || in_array(config("queue.connections.{$connection}.driver"), ['sync', 'null'], true)
            ? [self::FAIL, 'QUEUE_CONNECTION must not be sync: media optimisation, social publishing and webhooks run in Supervisor workers']
            : [self::PASS, 'QUEUE_CONNECTION='.$connection];
    }

    /**
     * A job running longer than retry_after is handed to a second worker while the first still runs.
     *
     * @return array{0: string, 1: string}
     */
    private function retryWindow(): array
    {
        $connection = (string) config('queue.default');
        $retryAfter = config("queue.connections.{$connection}.retry_after");

        if (! is_numeric($retryAfter)) {
            return [self::PASS, 'Driver has no retry_after window'];
        }

        $longest = $this->longestJobTimeout();

        return (int) $retryAfter > $longest
            ? [self::PASS, "retry_after {$retryAfter}s > longest job timeout {$longest}s"]
            : [self::FAIL, "retry_after ({$retryAfter}s) must exceed the longest job timeout ({$longest}s): set DB_QUEUE_RETRY_AFTER / REDIS_QUEUE_RETRY_AFTER"];
    }

    public function longestJobTimeout(): int
    {
        $longest = 60;

        foreach (File::files(app_path('Jobs')) as $file) {
            $class = 'App\\Jobs\\'.$file->getFilenameWithoutExtension();

            if (class_exists($class)) {
                $timeout = (new ReflectionClass($class))->getDefaultProperties()['timeout'] ?? null;
                $longest = is_int($timeout) ? max($longest, $timeout) : $longest;
            }
        }

        return $longest;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function scheduler(): array
    {
        $age = $this->heartbeat->ageInSeconds();

        return match (true) {
            $age === null => [self::FAIL, 'The scheduler has never run: add the cron entry (* * * * * php artisan schedule:run)'],
            ! $this->heartbeat->isFresh() => [self::FAIL, "Last scheduler run {$age}s ago: check cron"],
            default => [self::PASS, "Last scheduler run {$age}s ago"],
        };
    }
}
