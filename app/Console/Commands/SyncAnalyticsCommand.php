<?php

namespace App\Console\Commands;

use App\Services\Analytics\AnalyticsSync;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('analytics:sync')]
#[Description('Pull GA4 and Search Console reports into cached daily snapshots')]
class SyncAnalyticsCommand extends Command
{
    public function handle(AnalyticsSync $sync): int
    {
        foreach ($sync->run() as $source => $outcome) {
            $this->line(strtoupper($source).': '.$outcome);
        }

        return self::SUCCESS;
    }
}
