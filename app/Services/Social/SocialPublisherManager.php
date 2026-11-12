<?php

namespace App\Services\Social;

use App\Contracts\SocialPublisher;
use App\Enums\SocialPlatform;

/**
 * Resolves the direct-publishing driver of a platform; null for platforms that only support manual export.
 */
class SocialPublisherManager
{
    public function for(SocialPlatform $platform): ?SocialPublisher
    {
        return match ($platform) {
            SocialPlatform::LinkedinPage => app(LinkedInPublisher::class),
            SocialPlatform::FacebookPage => app(FacebookPublisher::class),
            SocialPlatform::InstagramBusiness => app(InstagramPublisher::class),
            SocialPlatform::X => app(XPublisher::class),
            SocialPlatform::Tiktok, SocialPlatform::Youtube => null,
        };
    }
}
