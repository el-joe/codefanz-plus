<?php

namespace App\Jobs;

use App\Services\Social\SocialMetrics;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Pulls metrics for posts published through the API in the last metrics_window_days and stores snapshots.
 */
class FetchSocialMetrics implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct()
    {
        $this->onQueue('social');
    }

    public function handle(SocialMetrics $metrics): void
    {
        $metrics->fetch();
    }
}
