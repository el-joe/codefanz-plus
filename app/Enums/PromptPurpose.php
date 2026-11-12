<?php

namespace App\Enums;

/**
 * What a prompt template writes. The purpose fixes the structured-output fields, which targets it can work on
 * and what the result becomes (a blog draft, a change request, or a private draft).
 */
enum PromptPurpose: string
{
    case BlogDraft = 'blog_draft';
    case ServiceCopy = 'service_copy';
    case SeoMeta = 'seo_meta';
    case SocialPost = 'social_post';
    case Email = 'email';
    case AdCopy = 'ad_copy';
    case Translation = 'translation';

    public function label(): string
    {
        return match ($this) {
            self::BlogDraft => __('Blog article'),
            self::ServiceCopy => __('Website copy'),
            self::SeoMeta => __('SEO metadata'),
            self::SocialPost => __('Social captions'),
            self::Email => __('Email draft'),
            self::AdCopy => __('Ad copy'),
            self::Translation => __('Translation'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::BlogDraft => __('Long-form, SEO-ready article draft'),
            self::ServiceCopy => __('Improve a page, service, product or case study'),
            self::SeoMeta => __('SEO title and meta description'),
            self::SocialPost => __('Post copy per platform'),
            self::Email => __('Campaigns and follow-ups'),
            self::AdCopy => __('Headlines and short ad text'),
            self::Translation => __('English to Arabic, keeping the glossary'),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::BlogDraft => 'layers',
            self::ServiceCopy => 'code',
            self::SeoMeta => 'trending-up',
            self::SocialPost => 'send',
            self::Email => 'users',
            self::AdCopy => 'sparkles',
            self::Translation => 'globe',
        };
    }

    /**
     * Whether the result is written into an existing website item (and therefore needs a target and an approval).
     */
    public function needsTarget(): bool
    {
        return in_array($this, [self::ServiceCopy, self::SeoMeta, self::Translation], true);
    }

    /**
     * Results that stay private drafts inside the studio (social posts are published from M8.2).
     */
    public function isPrivateOnly(): bool
    {
        return in_array($this, [self::SocialPost, self::Email, self::AdCopy], true);
    }

    /**
     * The AI feature the call is metered under (and paused by).
     */
    public function feature(): AiFeature
    {
        return match ($this) {
            self::SeoMeta => AiFeature::SeoAssistant,
            self::SocialPost => AiFeature::Social,
            default => AiFeature::ContentStudio,
        };
    }

    /**
     * Fixed output fields for purposes without a target (targets use their own fields).
     *
     * @return list<string>
     */
    public function outputFields(): array
    {
        return match ($this) {
            self::BlogDraft => ['title', 'excerpt', 'body', 'seo_title', 'seo_description'],
            self::SeoMeta => ['seo_title', 'seo_description'],
            self::SocialPost => ['text', 'hashtags', 'call_to_action'],
            self::Email => ['subject', 'preheader', 'body'],
            self::AdCopy => ['headline', 'description', 'call_to_action'],
            self::ServiceCopy, self::Translation => ['title', 'summary', 'body'],
        };
    }
}
