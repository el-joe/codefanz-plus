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
