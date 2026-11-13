<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Assistant conversations (and any other Prunable model) past their retention period.
Schedule::command('model:prune')->daily();

// Support tickets resolved for codefanz.support.auto_close_after_days are closed.
Schedule::command('tickets:close-resolved')->dailyAt('02:30');

// Payment links past their expiry; milestone paid amounts re-derived from the ledger as a safety net.
Schedule::command('payments:expire-links')->hourly();
Schedule::command('payments:reconcile')->dailyAt('03:10');

// Approved blog articles whose scheduled time has passed.
Schedule::command('posts:publish-scheduled')->everyMinute()->withoutOverlapping();

// SEO audit crawl (queued) and the daily GA4 / Search Console snapshot pull.
Schedule::command('seo:audit')->weeklyOn(1, '05:00');
Schedule::command('analytics:sync')->dailyAt('06:00')->withoutOverlapping();

// Social media (M8.2): approved posts due now are queued (queue "social"), tokens refreshed before they expire,
// metrics pulled for posts published through the APIs.
Schedule::command('social:publish-due')->everyMinute()->withoutOverlapping();
Schedule::command('social:refresh-tokens')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('social:fetch-metrics')->everySixHours()->withoutOverlapping();

// Freelance Radar (M10): API platforms are checked every five minutes; each platform's own cadence, hourly request
// budget and switch decide whether it is actually called. The digest goes out once a day.
Schedule::command('radar:fetch')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('radar:digest')->dailyAt((string) config('codefanz.radar.digest_time', '08:00'))->withoutOverlapping();
