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
