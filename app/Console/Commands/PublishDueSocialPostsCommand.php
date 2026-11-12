<?php

namespace App\Console\Commands;

use App\Services\Social\SocialDelivery;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('social:publish-due')]
#[Description('Queue approved social posts whose scheduled time has passed')]
class PublishDueSocialPostsCommand extends Command
{
    public function handle(SocialDelivery $delivery): int
    {
        if ($delivery->publishingPaused()) {
            $this->warn('Social publishing is paused by a kill switch.');

            return self::SUCCESS;
        }

        $count = $delivery->dispatchDue();

        $this->info("Queued {$count} social post(s).");

        return self::SUCCESS;
    }
}
