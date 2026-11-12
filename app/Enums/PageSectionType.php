<?php

namespace App\Enums;

/**
 * The fixed set of managed page sections (homepage, About, Careers). Admins reorder, hide and edit their copy;
 * list data comes from the catalog.
 */
enum PageSectionType: string
{
    case Hero = 'hero';
    case Services = 'services';
    case Technologies = 'technologies';
    case FeaturedProjects = 'featured_projects';
    case Products = 'products';
    case Industries = 'industries';
    case Process = 'process';
    case WhyUs = 'why_us';
    case Statistics = 'statistics';
    case Testimonials = 'testimonials';
    case LatestArticles = 'latest_articles';
    case Faq = 'faq';
    case Cta = 'cta';
    case AboutIntro = 'about_intro';
    case AboutStory = 'about_story';
    case AboutMission = 'about_mission';
    case AboutValues = 'about_values';
    case AboutTeam = 'about_team';
    case CareersPerks = 'careers_perks';

    /**
     * The section-managed page this type belongs to (see PageSection::PAGES).
     */
    public function page(): string
    {
        return match ($this) {
            self::AboutIntro, self::AboutStory, self::AboutMission, self::AboutValues, self::AboutTeam => 'about',
            self::CareersPerks => 'careers',
            default => 'home',
        };
    }

    /**
     * Types of one page in their default order.
     *
     * @return list<self>
     */
    public static function forPage(string $page): array
    {
        return array_values(array_filter(self::cases(), fn (self $type): bool => $type->page() === $page));
    }

    public function label(): string
    {
        return match ($this) {
            self::Hero => __('Hero'),
            self::Services => __('Services'),
            self::Technologies => __('Technologies'),
            self::FeaturedProjects => __('Featured projects'),
            self::Products => __('Software products'),
            self::Industries => __('Industries'),
            self::Process => __('Process'),
            self::WhyUs => __('Why CodeFanz'),
            self::Statistics => __('Statistics'),
            self::Testimonials => __('Testimonials'),
            self::LatestArticles => __('Latest articles'),
            self::Faq => __('FAQ'),
            self::Cta => __('Call to action'),
            self::AboutIntro => __('Introduction'),
            self::AboutStory => __('Our story'),
            self::AboutMission => __('Mission'),
            self::AboutValues => __('Values'),
            self::AboutTeam => __('Team disciplines'),
            self::CareersPerks => __('Why work with us'),
        };
    }

    /**
     * Where the section's content is managed (shown in the admin).
     */
    public function source(): string
    {
        return match ($this) {
            self::Hero, self::Process, self::WhyUs, self::Cta, self::AboutIntro, self::AboutMission => __('Copy on this screen'),
            self::AboutStory => __('Copy on this screen; shown once the story text is filled'),
            self::AboutValues, self::AboutTeam, self::CareersPerks => __('Rows on this screen; shown once at least one row is filled'),
            self::Services => __('Featured services first'),
            self::Technologies => __('Verified and expertise technologies'),
            self::FeaturedProjects => __('Featured public projects first'),
            self::Products => __('Published products'),
            self::Industries => __('Published industries'),
            self::Statistics => __('Published statistics'),
            self::Testimonials => __('Featured testimonials with client consent'),
            self::LatestArticles => __('Latest published blog articles'),
            self::Faq => __('Featured FAQs first'),
        };
    }

    /**
     * Sections that cannot be shown yet because their content type does not exist (every type is available since
     * the blog shipped in Milestone 7; kept for future section types).
     */
    public function isAvailable(): bool
    {
        return true;
    }

    /**
     * Built-in wording used when a language has no custom copy.
     *
     * @return array{eyebrow: string|null, heading: string|null, subheading: string|null, cta_label: string|null}
     */
    public function defaults(?string $locale = null): array
    {
        [$eyebrow, $heading, $subheading, $cta] = match ($this) {
            self::Hero => [__('Software development & digital solutions', [], $locale), __('Turning Ideas Into Powerful Digital Products', [], $locale), __('We design, build and scale web platforms, mobile apps, SaaS products and AI-powered automation — engineered for reliability and growth.', [], $locale), __('Start Your Project', [], $locale)],
            self::Services => [__('What we do', [], $locale), __('End-to-end software engineering', [], $locale), __('From the first workshop to long-term support — one accountable team.', [], $locale), __('View all services', [], $locale)],
            self::Technologies => [__('Our stack', [], $locale), __('The right stack for every product', [], $locale), __('Verified in delivered projects or backed by in-house expertise.', [], $locale), __('See all technologies', [], $locale)],
            self::FeaturedProjects => [__('Our work', [], $locale), __('Products we’re proud of', [], $locale), __('Real projects for real businesses.', [], $locale), __('View all projects', [], $locale)],
            self::Products => [__('Software products', [], $locale), __('Ready-made solutions, tailored to you', [], $locale), __('Proven platforms we license, customize and support.', [], $locale), __('Explore products', [], $locale)],
            self::Industries => [__('Industries', [], $locale), __('Built for your industry', [], $locale), __('Domain knowledge that fits how your industry really works.', [], $locale), __('See all industries', [], $locale)],
            self::Process => [__('How we work', [], $locale), __('A clear process from idea to launch', [], $locale), null, null],
            self::WhyUs => [__('Why CodeFanz', [], $locale), __('A partner that engineers for the long run', [], $locale), null, __('Start Your Project', [], $locale)],
            self::Statistics => [null, __('CodeFanz in numbers', [], $locale), null, null],
            self::Testimonials => [__('Client stories', [], $locale), __('What our clients say', [], $locale), null, __('Read more client stories', [], $locale)],
            self::LatestArticles => [__('Blog', [], $locale), __('Latest from our blog', [], $locale), null, __('Read the blog', [], $locale)],
            self::Faq => [__('FAQ', [], $locale), __('Questions, answered', [], $locale), null, __('See all questions', [], $locale)],
            self::Cta => [null, __('Have an idea? Let’s build it together.', [], $locale), __('Get a free preliminary estimate within 48 hours. No payment required.', [], $locale), __('Start Your Project', [], $locale)],
            self::AboutIntro => [__('About CodeFanz', [], $locale), __('Engineering digital products that last', [], $locale), __('CodeFanz is a software development company helping startups and established businesses design, build and scale web, mobile and AI-powered products.', [], $locale), null],
            self::AboutStory => [__('Our story', [], $locale), __('How CodeFanz started', [], $locale), null, null],
            self::AboutMission => [__('Our mission', [], $locale), __('Turn ideas into reliable, secure and scalable software that creates measurable business value.', [], $locale), null, null],
            self::AboutValues => [__('Our values', [], $locale), __('What guides every project', [], $locale), null, null],
            self::AboutTeam => [__('Our team', [], $locale), __('One team, every discipline', [], $locale), __('A dedicated squad is formed for each project — sized to your scope.', [], $locale), null],
            self::CareersPerks => [null, __('Why work with us', [], $locale), null, null],
        };

        return ['eyebrow' => $eyebrow, 'heading' => $heading, 'subheading' => $subheading, 'cta_label' => $cta];
    }

    /**
     * Default and maximum number of catalog items for list sections, or null when the section has no list.
     *
     * @return array{default: int, max: int}|null
     */
    public function limit(): ?array
    {
        return match ($this) {
            self::Services => ['default' => 6, 'max' => 12],
            self::Technologies => ['default' => 18, 'max' => 40],
            self::FeaturedProjects => ['default' => 3, 'max' => 9],
            self::Products => ['default' => 2, 'max' => 6],
            self::Industries => ['default' => 6, 'max' => 12],
            self::Statistics => ['default' => 4, 'max' => 8],
            self::Testimonials => ['default' => 3, 'max' => 9],
            self::Faq => ['default' => 5, 'max' => 12],
            self::LatestArticles => ['default' => 3, 'max' => 9],
            default => null,
        };
    }

    /**
     * Sections whose call to action may point at a custom URL (otherwise the contact email or the listing page).
     */
    public function hasCustomCtaUrl(): bool
    {
        return in_array($this, [self::Hero, self::WhyUs, self::Cta], true);
    }

    /**
     * Sections with editable check-list lines.
     */
    public function hasPoints(): bool
    {
        return in_array($this, [self::Hero, self::WhyUs], true);
    }

    /**
     * Sections with editable title + text rows.
     */
    public function hasItems(): bool
    {
        return in_array($this, [self::Process, self::WhyUs, self::AboutValues, self::AboutTeam, self::CareersPerks], true);
    }

    /**
     * Admin heading for the editable rows.
     */
    public function itemsLabel(): string
    {
        return match ($this) {
            self::Process => __('Process steps'),
            self::AboutValues => __('Values'),
            self::AboutTeam => __('Disciplines'),
            self::CareersPerks => __('Perks'),
            default => __('Reasons'),
        };
    }
}
