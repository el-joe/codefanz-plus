<?php

namespace App\Enums;

/**
 * Everything the SEO audit crawler checks. Category groups the dashboard cards; severity drives the health score.
 */
enum SeoIssueType: string
{
    case HttpStatus = 'http_status';
    case BrokenInternalLink = 'broken_internal_link';
    case BrokenExternalLink = 'broken_external_link';
    case MissingTitle = 'missing_title';
    case DuplicateTitle = 'duplicate_title';
    case TitleLength = 'title_length';
    case MissingDescription = 'missing_description';
    case DuplicateDescription = 'duplicate_description';
    case DescriptionLength = 'description_length';
    case MissingH1 = 'missing_h1';
    case MultipleH1 = 'multiple_h1';
    case MissingAlt = 'missing_alt';
    case ImageWithoutAvif = 'image_without_avif';
    case HreflangReciprocity = 'hreflang_reciprocity';
    case CanonicalMismatch = 'canonical_mismatch';
    case Noindex = 'noindex';
    case MixedContent = 'mixed_content';
    case IndexingBlocked = 'indexing_blocked';

    public function label(): string
    {
        return match ($this) {
            self::HttpStatus => __('Page does not return 200'),
            self::BrokenInternalLink => __('Broken internal link'),
            self::BrokenExternalLink => __('Broken external link'),
            self::MissingTitle => __('Missing title'),
            self::DuplicateTitle => __('Duplicate title'),
            self::TitleLength => __('Title length outside 30–60 characters'),
            self::MissingDescription => __('Missing meta description'),
            self::DuplicateDescription => __('Duplicate meta description'),
            self::DescriptionLength => __('Description length outside 70–160 characters'),
            self::MissingH1 => __('Missing H1 heading'),
            self::MultipleH1 => __('More than one H1 heading'),
            self::MissingAlt => __('Images without alt text'),
            self::ImageWithoutAvif => __('Large image without AVIF'),
            self::HreflangReciprocity => __('Hreflang not reciprocal'),
            self::CanonicalMismatch => __('Canonical points elsewhere'),
            self::Noindex => __('Indexable page marked noindex'),
            self::MixedContent => __('Mixed content (http on https)'),
            self::IndexingBlocked => __('Search engines blocked site-wide'),
        };
    }

    public function category(): string
    {
        return match ($this) {
            self::HttpStatus => 'crawl',
            self::BrokenInternalLink, self::BrokenExternalLink => 'links',
            self::MissingTitle, self::DuplicateTitle, self::TitleLength, self::MissingDescription, self::DuplicateDescription, self::DescriptionLength => 'meta',
            self::MissingH1, self::MultipleH1 => 'content',
            self::MissingAlt, self::ImageWithoutAvif => 'images',
            self::HreflangReciprocity => 'international',
            self::CanonicalMismatch, self::Noindex, self::IndexingBlocked => 'indexing',
            self::MixedContent => 'security',
        };
    }

    public function severity(): SeoSeverity
    {
        return match ($this) {
            self::HttpStatus, self::BrokenInternalLink, self::MissingTitle, self::Noindex, self::MixedContent => SeoSeverity::Error,
            self::BrokenExternalLink, self::DuplicateTitle, self::MissingDescription, self::DuplicateDescription, self::MissingH1,
            self::MissingAlt, self::HreflangReciprocity, self::CanonicalMismatch => SeoSeverity::Warning,
            self::TitleLength, self::DescriptionLength, self::MultipleH1, self::ImageWithoutAvif, self::IndexingBlocked => SeoSeverity::Notice,
        };
    }

    /**
     * Issues an AI suggestion can address by proposing new SEO title / description text.
     */
    public function isAiFixable(): bool
    {
        return in_array($this, [self::MissingTitle, self::DuplicateTitle, self::TitleLength, self::MissingDescription, self::DuplicateDescription, self::DescriptionLength], true);
    }

    /**
     * @return array<string, string>
     */
    public static function categories(): array
    {
        return [
            'crawl' => __('Status codes'),
            'links' => __('Links'),
            'meta' => __('Titles & descriptions'),
            'content' => __('Headings'),
            'images' => __('Images'),
            'international' => __('Hreflang'),
            'indexing' => __('Indexing & canonicals'),
            'security' => __('Mixed content'),
        ];
    }
}
