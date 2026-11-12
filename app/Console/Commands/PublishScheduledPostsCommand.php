<?php

namespace App\Console\Commands;

use App\Actions\Blog\PostWorkflow;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('posts:publish-scheduled')]
#[Description('Publish approved blog articles whose scheduled time has passed')]
class PublishScheduledPostsCommand extends Command
{
    public function handle(PostWorkflow $workflow): int
    {
        $count = $workflow->publishDue();

        $this->info("Published {$count} scheduled article(s).");

        return self::SUCCESS;
    }
}
