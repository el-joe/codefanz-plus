<?php

namespace App\Services\Seo;

use App\Enums\ContentStatus;
use App\Enums\PageStatus;
use App\Models\Career;
use App\Models\Page;
use App\Models\PortfolioProject;
use App\Models\Post;
use App\Models\Product;
use App\Models\Service;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The indexable parts of the public site: content types with a detail URL per translation (keyed by morph alias)
 * and the fixed site pages (keyed by route name). Sitemaps, the SEO editor, the audit crawler and automatic
 * slug redirects all read this one registry.
 */
class SeoTargets
{
    /**
     * @var array<string, array{model: class-string<Model>, route: string, title: string, sitemap: string, label: string, edit: string}>
     */
    private const TYPES = [
        'page' => ['model' => Page::class, 'route' => 'pages.show', 'title' => 'title', 'sitemap' => 'pages', 'label' => 'Pages', 'edit' => 'admin.pages.edit'],
        'service' => ['model' => Service::class, 'route' => 'services.show', 'title' => 'title', 'sitemap' => 'services', 'label' => 'Services', 'edit' => 'admin.services.edit'],
        'portfolio_project' => ['model' => PortfolioProject::class, 'route' => 'portfolio.show', 'title' => 'title', 'sitemap' => 'portfolio', 'label' => 'Portfolio', 'edit' => 'admin.portfolio.edit'],
        'product' => ['model' => Product::class, 'route' => 'products.show', 'title' => 'name', 'sitemap' => 'products', 'label' => 'Software products', 'edit' => 'admin.products.edit'],
        'career' => ['model' => Career::class, 'route' => 'careers.show', 'title' => 'title', 'sitemap' => 'careers', 'label' => 'Careers', 'edit' => 'admin.careers.edit'],
        'post' => ['model' => Post::class, 'route' => 'blog.show', 'title' => 'title', 'sitemap' => 'posts', 'label' => 'Blog', 'edit' => 'admin.posts.edit'],
    ];

    /**
     * Fixed site pages: route name => English label (translated on display).
     *
     * @var array<string, string>
     */
    private const STATIC_ROUTES = [
        'home' => 'Home',
        'about' => 'About',
        'services.index' => 'Services',
        'technologies.index' => 'Technologies',
        'industries.index' => 'Industries',
        'portfolio.index' => 'Portfolio',
        'products.index' => 'Software products',
        'blog.index' => 'Blog',
        'careers.index' => 'Careers',
        'faq.index' => 'FAQ',
        'testimonials.index' => 'Testimonials',
        'contact' => 'Contact',
        'quote' => 'Request a Quote',
        'assistant' => 'AI Project Assistant',
    ];

    /**
     * @return list<string>
     */
    public function types(): array
    {
        return array_keys(self::TYPES);
    }

    public function has(string $type): bool
    {
        return isset(self::TYPES[$type]);
    }

    /**
     * @return array<string, string>
     */
    public function staticRoutes(): array
    {
        return self::STATIC_ROUTES;
    }

    public function isStaticRoute(string $routeName): bool
    {
        return isset(self::STATIC_ROUTES[$routeName]);
    }

    /**
     * @return class-string<Model>
     */
    public function model(string $type): string
    {
        return self::TYPES[$type]['model'];
    }

    public function route(string $type): string
    {
        return self::TYPES[$type]['route'];
    }

    public function titleField(string $type): string
    {
        return self::TYPES[$type]['title'];
    }

    public function sitemapName(string $type): string
    {
        return self::TYPES[$type]['sitemap'];
    }

    public function typeForSitemap(string $name): ?string
    {
        foreach (self::TYPES as $type => $definition) {
            if ($definition['sitemap'] === $name) {
                return $type;
            }
        }

        return null;
    }

    public function label(string $type): string
    {
        return __(self::TYPES[$type]['label'] ?? self::STATIC_ROUTES[$type] ?? $type);
    }

    public function typeOf(Model $model): ?string
    {
        $alias = $model->getMorphClass();

        return $this->has($alias) ? $alias : null;
    }

    /**
     * Records that have a public URL at all (published; open for careers). Unlisted projects are reachable but
     * never indexable, so they are excluded here and reported by isIndexable().
     *
     * @return Builder<Model>
     */
    public function publicQuery(string $type): Builder
    {
        return match ($type) {
            'page' => Page::query()->where('status', PageStatus::Published),
            'service' => Service::query()->published(),
            'portfolio_project' => PortfolioProject::query()->published(),
            'product' => Product::query()->published(),
            'career' => Career::query()->open(),
            'post' => Post::query()->published(),
        };
    }

    /**
     * Whether a record would be served to visitors at all.
     */
    public function isPublic(Model $model): bool
    {
        return match (true) {
            $model instanceof Page => $model->status === PageStatus::Published,
            $model instanceof Service, $model instanceof Product, $model instanceof PortfolioProject => $model->status === ContentStatus::Published,
            $model instanceof Career => $model->isOpen(),
            $model instanceof Post => $model->isPubliclyVisible(),
            default => false,
        };
    }

    /**
     * Null when the record may be indexed, otherwise the (translated) reason it is not.
     */
    public function notIndexableReason(Model $model, bool $noindex = false, ?string $canonical = null, ?string $selfUrl = null): ?string
    {
        return match (true) {
            ! $this->isPublic($model) => __('Not published'),
            $model instanceof PortfolioProject && ! $model->isPubliclyListed() => __('Unlisted (link only)'),
            $noindex => __('Set to noindex'),
            $canonical !== null && $canonical !== '' && $selfUrl !== null && rtrim($canonical, '/') !== rtrim($selfUrl, '/') => __('Canonical points to another URL'),
            default => null,
        };
    }

    public function url(string $type, string $slug, string $locale): string
    {
        return route(self::TYPES[$type]['route'], ['locale' => $locale, 'slug' => $slug]);
    }

    public function path(string $type, string $slug, string $locale): string
    {
        return route(self::TYPES[$type]['route'], ['locale' => $locale, 'slug' => $slug], false);
    }

    public function editUrl(string $type, Model $model): string
    {
        return route(self::TYPES[$type]['edit'], $model);
    }
}
