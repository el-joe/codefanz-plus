<?php

namespace App\Services;

use App\Enums\ContentStatus;
use App\Enums\MenuLinkType;
use App\Enums\MenuLocation;
use App\Enums\PageStatus;
use App\Models\Career;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\PortfolioProject;
use App\Models\Product;
use App\Models\Service;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

/**
 * Turns a menu into plain arrays for the public layout, resolved for one locale and cached.
 *
 * Items whose target cannot be shown in that locale (hidden, unpublished, untranslated, closed or trashed)
 * are dropped together with their children. Any write to menus or linkable content bumps the cache version.
 */
class MenuResolver
{
    public const VERSION_KEY = 'menus.version';

    /**
     * Content routes for linkable morph aliases.
     *
     * @var array<string, string>
     */
    private const LINKABLE_ROUTES = [
        'page' => 'pages.show',
        'service' => 'services.show',
        'product' => 'products.show',
        'portfolio_project' => 'portfolio.show',
        'career' => 'careers.show',
    ];

    /**
     * @return list<array{label: string, url: string, new_tab: bool, children: list<array{label: string, url: string, new_tab: bool, children: list<mixed>}>}>
     */
    public function resolve(MenuLocation $location, ?string $locale = null): array
    {
        $locale ??= app()->getLocale();
        $key = sprintf('menus.%s.%s.%s', self::version(), $location->value, $locale);

        return Cache::remember($key, now()->addDay(), fn (): array => $this->build($location, $locale));
    }

    /**
     * Resolved items, or the built-in links when the menu has none (fresh installs).
     *
     * @return list<array{label: string, url: string, new_tab: bool, children: list<mixed>}>
     */
    public function resolveOrFallback(MenuLocation $location, ?string $locale = null): array
    {
        return $this->resolve($location, $locale) ?: $this->fallback($location);
    }

    public static function flush(): void
    {
        Cache::forever(self::VERSION_KEY, self::version() + 1);
    }

    private static function version(): int
    {
        return (int) Cache::get(self::VERSION_KEY, 1);
    }

    /**
     * @return list<array{label: string, url: string, new_tab: bool, children: list<mixed>}>
     */
    private function build(MenuLocation $location, string $locale): array
    {
        $menu = Menu::query()
            ->where('location', $location->value)
            ->with(['items' => fn ($query) => $query->where('is_visible', true)->with([
                'translations',
                'linkable' => fn (MorphTo $morph) => $morph->morphWith([
                    Page::class => ['translations'],
                    Service::class => ['translations'],
                    Product::class => ['translations'],
                    PortfolioProject::class => ['translations'],
                    Career::class => ['translations'],
                ]),
            ])])
            ->first();

        if ($menu === null) {
            return [];
        }

        $byParent = $menu->items->groupBy(fn (MenuItem $item): int => (int) $item->parent_id);
        $resolved = [];

        foreach ($byParent->get(0, collect()) as $item) {
            $entry = $this->entry($item, $locale);

            if ($entry === null) {
                continue;
            }

            if ($location->allowsChildren()) {
                foreach ($byParent->get($item->id, collect()) as $child) {
                    if ($childEntry = $this->entry($child, $locale)) {
                        $entry['children'][] = $childEntry;
                    }
                }
            }

            $resolved[] = $entry;
        }

        return $resolved;
    }

    /**
     * @return array{label: string, url: string, new_tab: bool, children: list<mixed>}|null
     */
    private function entry(MenuItem $item, string $locale): ?array
    {
        $label = $item->translation($locale)?->label;
        $url = $this->url($item, $locale);

        if (blank($label) || $url === null) {
            return null;
        }

        return ['label' => $label, 'url' => $url, 'new_tab' => $item->open_in_new_tab, 'children' => []];
    }

    public function url(MenuItem $item, string $locale): ?string
    {
        return match ($item->link_type) {
            MenuLinkType::Route => $this->routeUrl($item, $locale),
            MenuLinkType::Linkable => $this->linkableUrl($item->linkable, $item->linkable_type, $locale),
            MenuLinkType::Url => self::isSafeExternalUrl((string) $item->url) ? $item->url : null,
        };
    }

    public static function isSafeExternalUrl(string $url): bool
    {
        return parse_url($url, PHP_URL_SCHEME) === 'https' && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    private function routeUrl(MenuItem $item, string $locale): ?string
    {
        if (! array_key_exists((string) $item->route_name, MenuItem::ROUTES) || ! Route::has($item->route_name)) {
            return null;
        }

        $query = array_filter((array) $item->route_params, fn (mixed $value, mixed $key): bool => is_string($key) && is_scalar($value) && $key !== 'locale', ARRAY_FILTER_USE_BOTH);

        return route($item->route_name, ['locale' => $locale, ...$query]);
    }

    private function linkableUrl(?Model $linkable, ?string $type, string $locale): ?string
    {
        if ($linkable === null || ! isset(self::LINKABLE_ROUTES[$type]) || ! $this->isPublic($linkable)) {
            return null;
        }

        $translation = $linkable->translations->firstWhere('locale', $locale);

        return $translation?->slug ? route(self::LINKABLE_ROUTES[$type], ['locale' => $locale, 'slug' => $translation->slug]) : null;
    }

    private function isPublic(Model $linkable): bool
    {
        return match (true) {
            $linkable instanceof Page => $linkable->status === PageStatus::Published,
            $linkable instanceof Career => $linkable->isOpen(),
            default => $linkable->getAttribute('status') === ContentStatus::Published,
        };
    }

    /**
     * Built-in links used until an admin fills a menu.
     *
     * @return list<array{label: string, url: string, new_tab: bool, children: list<mixed>}>
     */
    public function fallback(MenuLocation $location): array
    {
        if ($location === MenuLocation::FooterLegal) {
            return $this->legalPages(app()->getLocale());
        }

        $routes = match ($location) {
            MenuLocation::Header => ['services.index' => __('Services'), 'portfolio.index' => __('Portfolio'), 'products.index' => __('Products'), 'technologies.index' => __('Technologies'), 'about' => __('About')],
            MenuLocation::FooterServices => ['services.index' => __('All services'), 'technologies.index' => __('Technologies'), 'industries.index' => __('Industries')],
            MenuLocation::FooterCompany => ['about' => __('About Us'), 'portfolio.index' => __('Portfolio'), 'careers.index' => __('Careers'), 'testimonials.index' => __('Testimonials'), 'contact' => __('Contact')],
            MenuLocation::FooterResources => ['products.index' => __('Products'), 'industries.index' => __('Industries'), 'faq.index' => __('FAQ')],
        };

        return collect($routes)
            ->map(fn (string $label, string $name): array => ['label' => $label, 'url' => route($name), 'new_tab' => false, 'children' => []])
            ->values()
            ->all();
    }

    /**
     * Published legal pages (template "legal") in this locale, used until the legal footer menu is filled.
     *
     * @return list<array{label: string, url: string, new_tab: bool, children: list<mixed>}>
     */
    private function legalPages(string $locale): array
    {
        $key = sprintf('menus.%s.legal-fallback.%s', self::version(), $locale);

        return Cache::remember($key, now()->addDay(), fn (): array => PageTranslation::query()
            ->where('locale', $locale)
            ->whereHas('page', fn ($query) => $query->where('template', 'legal')->where('status', PageStatus::Published))
            ->orderBy('page_id')
            ->get(['title', 'slug'])
            ->map(fn (PageTranslation $translation): array => ['label' => $translation->title, 'url' => route('pages.show', ['locale' => $locale, 'slug' => $translation->slug]), 'new_tab' => false, 'children' => []])
            ->all());
    }
}
