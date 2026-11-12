<?php

namespace App\Contracts;

use App\Exceptions\SocialApiException;
use App\Models\SocialAccount;
use App\Models\SocialPostVariant;
use App\Services\Social\PublishResult;

/**
 * A direct-publishing driver for one platform (LinkedIn Pages, Facebook Pages, Instagram, X). Drivers only talk to
 * the platform through Laravel's Http client; App\Services\Social\SocialDelivery owns the state machine, retries
 * and idempotency. Intermediate ids (uploaded images, Instagram containers) are written to the variant's
 * platform_state as soon as they exist so a retry reuses them instead of creating new ones.
 *
 * Resolved by App\Services\Social\SocialPublisherManager.
 */
interface SocialPublisher
{
    /**
     * @throws SocialApiException
     */
    public function publish(SocialPostVariant $variant, SocialAccount $account): PublishResult;

    /**
     * Metrics the platform reports for a published post. A null value means "not reported", never zero.
     *
     * @return array{impressions: int|null, engagements: int|null, clicks: int|null, raw: array<string, mixed>}
     *
     * @throws SocialApiException
     */
    public function metrics(SocialPostVariant $variant, SocialAccount $account): array;

    /**
     * A harmless authenticated read proving the token still works.
     *
     * @throws SocialApiException
     */
    public function verify(SocialAccount $account): void;
}
