<?php

namespace App\Jobs;

use App\Services\Social\SocialDelivery;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Publishes one approved variant (queue "social", unique per variant). Retries are driven by SocialDelivery: only
 * errors that certainly did not create a post are retried, with exponential backoff; rate-limited jobs wait for
 * the platform's window.
 */
class PublishSocialPost implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public int $uniqueFor = 3600;

    public function __construct(public int $variantId)
    {
        $this->onQueue('social');
    }

    public function uniqueId(): string
    {
        return (string) $this->variantId;
    }

    public function tries(): int
    {
        // Rate-limit waits also count as attempts, so leave room above the publish attempts.
        return (int) config('codefanz.social.publish_tries', 5) * 3;
    }

    public function handle(SocialDelivery $delivery): void
    {
        $delay = $delivery->deliver($this->variantId, $this->attempts(), (int) config('codefanz.social.publish_tries', 5));

        if ($delay !== null) {
            $this->release($delay);
        }
    }
}
