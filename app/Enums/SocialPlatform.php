<?php

namespace App\Enums;

/**
 * Social networks CodeFanz can prepare posts for. Limits follow each platform's published rules; only LinkedIn
 * Pages, Facebook Pages, Instagram professional accounts and X have direct-publishing drivers. TikTok and YouTube
 * need audited apps and video uploads, so they always run in manual export mode.
 */
enum SocialPlatform: string
{
    case LinkedinPage = 'linkedin_page';
    case FacebookPage = 'facebook_page';
    case InstagramBusiness = 'instagram_business';
    case X = 'x';
    case Tiktok = 'tiktok';
    case Youtube = 'youtube';

    public function label(): string
    {
        return match ($this) {
            self::LinkedinPage => __('LinkedIn Page'),
            self::FacebookPage => __('Facebook Page'),
            self::InstagramBusiness => __('Instagram'),
            self::X => __('X'),
            self::Tiktok => __('TikTok'),
            self::Youtube => __('YouTube'),
        };
    }

    /**
     * Short name for calendar chips.
     */
    public function shortLabel(): string
    {
        return match ($this) {
            self::LinkedinPage => 'LinkedIn',
            self::FacebookPage => 'Facebook',
            self::InstagramBusiness => 'Instagram',
            self::X => 'X',
            self::Tiktok => 'TikTok',
            self::Youtube => 'YouTube',
        };
    }

    /**
     * utm_source value of links shared on this platform.
     */
    public function utmSource(): string
    {
        return strtolower($this->shortLabel());
    }

    /**
     * The OAuth app (Settings → Social apps) the platform connects through, or null for manual-only platforms.
     */
    public function provider(): ?string
    {
        return match ($this) {
            self::LinkedinPage => 'linkedin',
            self::FacebookPage, self::InstagramBusiness => 'meta',
            self::X => 'x',
            self::Tiktok, self::Youtube => null,
        };
    }

    public function supportsDirectPublishing(): bool
    {
        return $this->provider() !== null;
    }

    /**
     * Maximum length of the composed post text (caption + hashtags + appended link).
     */
    public function maxLength(): int
    {
        return match ($this) {
            self::LinkedinPage => 3000,
            self::FacebookPage => 63206,
            self::InstagramBusiness => 2200,
            self::X => 280,
            self::Tiktok => 2200,
            self::Youtube => 5000,
        };
    }

    public function maxHashtags(): ?int
    {
        return match ($this) {
            self::InstagramBusiness => 30,
            self::Youtube => 15,
            default => null,
        };
    }

    public function maxMedia(): int
    {
        return match ($this) {
            self::LinkedinPage => 9,
            self::FacebookPage => 10,
            self::InstagramBusiness => 10,
            self::X => 4,
            self::Tiktok => 35,
            self::Youtube => 1,
        };
    }

    public function requiresMedia(): bool
    {
        return in_array($this, [self::InstagramBusiness, self::Tiktok, self::Youtube], true);
    }

    /**
     * Instagram's API only accepts JPEG images with an aspect ratio between 4:5 and 1.91:1.
     *
     * @return array{min: float, max: float}|null
     */
    public function aspectRatioRange(): ?array
    {
        return $this === self::InstagramBusiness ? ['min' => 0.8, 'max' => 1.91] : null;
    }

    /**
     * @return list<string>|null
     */
    public function allowedMimeTypes(): ?array
    {
        return match ($this) {
            self::InstagramBusiness => ['image/jpeg'],
            self::LinkedinPage, self::FacebookPage, self::X => ['image/jpeg', 'image/png', 'image/gif'],
            default => null,
        };
    }

    public function mediaHint(): string
    {
        return match ($this) {
            self::LinkedinPage => __('Up to 9 images · 1.91:1 (1200×627) or square works best'),
            self::FacebookPage => __('Up to 10 images · 1.91:1 or square'),
            self::InstagramBusiness => __('1–10 JPEG images · between 4:5 portrait and 1.91:1 landscape'),
            self::X => __('Up to 4 images · 16:9 works best'),
            self::Tiktok => __('Video or photo carousel · 9:16 · post it from the TikTok app'),
            self::Youtube => __('Video required · upload it in YouTube Studio'),
        };
    }

    public function supportsFirstComment(): bool
    {
        return in_array($this, [self::LinkedinPage, self::FacebookPage, self::InstagramBusiness, self::X], true);
    }

    /**
     * Whether the link is written into the text (X, YouTube) rather than sent as a link attachment (LinkedIn,
     * Facebook). Instagram and TikTok captions cannot hold clickable links, so the link is left out there.
     */
    public function linkPlacement(): string
    {
        return match ($this) {
            self::X, self::Youtube => 'text',
            self::LinkedinPage, self::FacebookPage => 'attachment',
            self::InstagramBusiness, self::Tiktok => 'none',
        };
    }

    /**
     * X shortens every link to 23 characters (t.co) when counting the length.
     */
    public function linkWeight(): ?int
    {
        return $this === self::X ? 23 : null;
    }

    public function supportsMetrics(): bool
    {
        return $this->supportsDirectPublishing();
    }

    public function postUrlPattern(): string
    {
        return match ($this) {
            self::LinkedinPage => 'https://www.linkedin.com/',
            self::FacebookPage => 'https://www.facebook.com/',
            self::InstagramBusiness => 'https://www.instagram.com/',
            self::X => 'https://x.com/',
            self::Tiktok => 'https://www.tiktok.com/',
            self::Youtube => 'https://www.youtube.com/',
        };
    }

    /**
     * Hosts a "posted manually" URL may point to.
     *
     * @return list<string>
     */
    public function hosts(): array
    {
        return match ($this) {
            self::LinkedinPage => ['linkedin.com', 'lnkd.in'],
            self::FacebookPage => ['facebook.com', 'fb.com', 'fb.watch'],
            self::InstagramBusiness => ['instagram.com'],
            self::X => ['x.com', 'twitter.com'],
            self::Tiktok => ['tiktok.com'],
            self::Youtube => ['youtube.com', 'youtu.be'],
        };
    }

    /**
     * @return list<self>
     */
    public static function forProvider(string $provider): array
    {
        return array_values(array_filter(self::cases(), fn (self $platform): bool => $platform->provider() === $provider));
    }
}
