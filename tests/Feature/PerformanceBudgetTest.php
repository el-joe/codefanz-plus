<?php

use App\Services\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| M11 performance regressions: settings that are not stored were never cached (null results are not cached), so
| every page queried and re-wrote the cache for each of them; fonts preloaded every family and shipped WOFF rules
| that overrode the preloaded WOFF2 files.
*/

/**
 * @return list<string>
 */
function queriesDuring(Closure $callback): array
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $callback();
    $queries = array_column(DB::getQueryLog(), 'query');
    DB::disableQueryLog();

    return $queries;
}

test('a setting that is not stored is cached like any other', function () {
    $settings = app(Settings::class);

    expect(queriesDuring(fn () => $settings->get('integrations', 'never_saved')))->toHaveCount(1);

    app()->forgetScopedInstances();

    expect(queriesDuring(fn () => expect($settings->get('integrations', 'never_saved', 'fallback'))->toBe('fallback')))->toBe([]);

    $settings->set('integrations', 'never_saved', 'now-saved');
    expect($settings->get('integrations', 'never_saved'))->toBe('now-saved');
});

test('repeated reads of a setting in one request hit the database cache store once', function () {
    config(['cache.default' => 'database']);
    $settings = app(Settings::class);
    $settings->get('contact', 'email');
    app()->forgetScopedInstances();

    $queries = queriesDuring(function () use ($settings): void {
        foreach (range(1, 5) as $read) {
            $settings->get('contact', 'email');
        }
    });

    expect($queries)->toHaveCount(1)->and($queries[0])->toContain('from `cache`');
});

test('warm public pages do not query settings again', function (string $locale) {
    $this->get(route('home', ['locale' => $locale]))->assertSuccessful();
    app()->forgetScopedInstances();

    $queries = queriesDuring(fn () => $this->get(route('home', ['locale' => $locale]))->assertSuccessful());

    expect(collect($queries)->filter(fn (string $sql): bool => str_contains($sql, 'from `settings`'))->all())->toBe([])
        ->and(count($queries))->toBeLessThanOrEqual(25);
})->with(['en', 'ar']);

test('english pages preload only the latin font and arabic pages only the arabic font', function () {
    $english = $this->get(route('home', ['locale' => 'en']))->getContent();
    $arabic = $this->get(route('home', ['locale' => 'ar']))->getContent();

    preg_match_all('/<link rel="preload" as="font" href="[^"]*\/(?<file>[a-z-]+)-\d{3}-normal-[^"]+\.woff2"/', $english, $englishPreloads);
    preg_match_all('/<link rel="preload" as="font" href="[^"]*\/(?<file>[a-z-]+)-\d{3}-normal-[^"]+\.woff2"/', $arabic, $arabicPreloads);

    expect(array_unique($englishPreloads['file']))->toBe(['plus-jakarta-sans'])
        ->and(array_unique($arabicPreloads['file']))->toBe(['cairo'])
        ->and($english)->toContain('format("woff2")')->not->toContain('format("woff")')
        ->and($arabic)->not->toContain('format("woff")')
        ->and($english)->toContain('font-display: swap');
});

test('the unread notification count used by every admin and portal page has a covering index', function () {
    expect(Schema::hasIndex('notifications', ['notifiable_type', 'notifiable_id', 'read_at']))->toBeTrue();
});
