<?php

namespace App\Console\Commands;

use App\Jobs\FetchSocialMetrics;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('social:fetch-metrics')]
#[Description('Pull engagement metrics for social posts published through the platform APIs')]
class FetchSocialMetricsCommand extends Command
{
    public function handle(): int
    {
        FetchSocialMetrics::dispatch();

        $this->info('Social metrics fetch queued.');

        return self::SUCCESS;
    }
}
