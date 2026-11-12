<?php

namespace App\Services\Seo;

use App\Models\Language;
use App\Models\Post;
use App\Models\SeoMeta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * XML sitemaps: an index plus one child per content type and language ("services-en"), each URL with lastmod and
 * xhtml:link hreflang alternates. Only published, indexable, self-canonical URLs are listed (no drafts, closed
 * positions, unlisted projects, noindex or canonicalised-away translations). Rendered XML is cached as plain
 * strings and flushed whenever listed content or SEO settings change.
 */
class SitemapBuilder
{
    private const CACHE_PREFIX = 'seo:sitemap:';

    public function __construct(private SeoTargets $targets) {}

    /**
     * Child sitemap names ("static-en", "posts-ar", …) that have at least one URL.
     *
     * @return list<string>
     */
    public function childNames(): array
    {
        $names = [];

        foreach (Language::active()->keys() as $locale) {
            foreach (['static', ...array_map(fn (string $type): string => $this->targets->sitemapName($type), $this->targets->types())] as $name) {
                if ($this->entries($name, $locale) !== []) {
                    $names[] = $name.'-'.$locale;
                }
            }
        }

        return $names;
    }

    public function index(): string
    {
        return Cache::rememberForever(self::CACHE_PREFIX.'index', function (): string {
            $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

            foreach ($this->childNames() as $name) {
                $xml .= '  <sitemap><loc>'.$this->escape(route('sitemap.child', ['name' => $name])).'</loc></sitemap>'."\n";
            }

            return $xml.'</sitemapindex>'."\n";
        });
    }

    /**
     * Child sitemap XML, or null for an unknown name.
     */
    public function child(string $name): ?string
    {
        if (preg_match('/^([a-z]+)-([a-z]{2})$/', $name, $matches) !== 1
            || ! Language::active()->has($matches[2])
            || ($matches[1] !== 'static' && $this->targets->typeForSitemap($matches[1]) === null)) {
            return null;
        }

        return Cache::rememberForever(self::CACHE_PREFIX.$name, function () use ($matches): string {
            $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
                .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">'."\n";

            foreach ($this->entries($matches[1], $matches[2]) as $entry) {
                $xml .= "  <url>\n    <loc>".$this->escape($entry['loc'])."</loc>\n";

                if ($entry['lastmod'] !== null) {
                    $xml .= '    <lastmod>'.$entry['lastmod']."</lastmod>\n";
                }

                foreach ($entry['alternates'] as $hreflang => $url) {
                    $xml .= '    <xhtml:link rel="alternate" hreflang="'.$this->escape($hreflang).'" href="'.$this->escape($url).'"/>'."\n";
                }

                $xml .= "  </url>\n";
            }

            return $xml.'</urlset>'."\n";
        });
    }

    /**
     * Every listed URL across all sitemaps (used by the SEO audit crawler).
     *
     * @return list<array{loc: string, locale: string, type: string, id: int|null, route_key: string|null}>
     */
    public function allUrls(): array
    {
        $urls = [];

        foreach (Language::active()->keys() as $locale) {
            foreach (['static', ...array_map(fn (string $type): string => $this->targets->sitemapName($type), $this->targets->types())] as $name) {
                foreach ($this->entries($name, $locale) as $entry) {
                    $urls[] = ['loc' => $entry['loc'], 'locale' => $locale, 'type' => $entry['type'], 'id' => $entry['id'], 'route_key' => $entry['route_key']];
                }
            }
        }

        return $urls;
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_PREFIX.'index');

        foreach (Language::active()->keys() as $locale) {
            foreach (['static', 'pages', 'services', 'portfolio', 'products', 'careers', 'posts'] as $name) {
                Cache::forget(self::CACHE_PREFIX.$name.'-'.$locale);
            }
        }
    }

    /**
     * @return list<array{loc: string, lastmod: string|null, alternates: array<string, string>, type: string, id: int|null, route_key: string|null}>
     */
    private function entries(string $name, string $locale): array
    {
        return $name === 'static' ? $this->staticEntries($locale) : $this->contentEntries((string) $this->targets->typeForSitemap($name), $locale);
    }

    /**
     * @return list<array{loc: string, lastmod: string|null, alternates: array<string, string>, type: string, id: int|null, route_key: string|null}>
     */
    private function staticEntries(string $locale): array
    {
        $locales = Language::active()->keys()->all();
        $excluded = SeoMeta::query()->whereNotNull('route_key')
            ->where(fn ($query) => $query->where('noindex', true)->orWhereNotNull('canonical_url'))
            ->get(['route_key', 'locale'])
            ->map(fn (SeoMeta $meta): string => $meta->route_key.':'.$meta->locale)
            ->flip();

        $entries = [];

        foreach (array_keys($this->targets->staticRoutes()) as $routeName) {
            if ($excluded->has($routeName.':'.$locale)) {
                continue;
            }

            $alternates = [];

            foreach ($locales as $code) {
                if (! $excluded->has($routeName.':'.$code)) {
                    $alternates[$code] = route($routeName, ['locale' => $code]);
                }
            }

            $entries[] = [
                'loc' => route($routeName, ['locale' => $locale]),
                'lastmod' => null,
                'alternates' => $this->withDefault($alternates),
                'type' => 'route',
                'id' => null,
                'route_key' => $routeName,
            ];
        }

        return $entries;
    }

    /**
     * @return list<array{loc: string, lastmod: string|null, alternates: array<string, string>, type: string, id: int|null, route_key: string|null}>
     */
    private function contentEntries(string $type, string $locale): array
    {
        $records = $this->targets->publicQuery($type)
            ->whereHas('translations', fn ($query) => $query->where('locale', $locale))
            ->with('translations')
            ->orderBy('id')
            ->get();

        $morph = $records->first()?->getMorphClass();
        $excluded = $morph === null ? collect() : SeoMeta::query()
            ->where('seoable_type', $morph)
            ->whereIn('seoable_id', $records->modelKeys())
            ->where(fn ($query) => $query->where('noindex', true)->orWhereNotNull('canonical_url'))
            ->get(['seoable_id', 'locale'])
            ->map(fn (SeoMeta $meta): string => $meta->seoable_id.':'.$meta->locale)
            ->flip();

        $entries = [];

        foreach ($records as $record) {
            if ($this->targets->notIndexableReason($record) !== null) {
                continue;
            }

            $alternates = [];

            foreach ($record->translations as $translation) {
                if ($this->isListable($record, $translation, $excluded)) {
                    $alternates[$translation->locale] = $this->targets->url($type, $translation->slug, $translation->locale);
                }
            }

            if (! isset($alternates[$locale])) {
                continue;
            }

            $entries[] = [
                'loc' => $alternates[$locale],
                'lastmod' => ($record->updated_at ?? $record->published_at)?->toAtomString(),
                'alternates' => $this->withDefault($alternates),
                'type' => $type,
                'id' => $record->getKey(),
                'route_key' => null,
            ];
        }

        return $entries;
    }

    /**
     * @param  Collection<string, int>  $excluded
     */
    private function isListable(Model $record, Model $translation, $excluded): bool
    {
        if ($excluded->has($record->getKey().':'.$translation->locale)) {
            return false;
        }

        return ! ($record instanceof Post && filled($translation->canonical_url));
    }

    /**
     * Alternates plus x-default (the default language) when there is more than one language.
     *
     * @param  array<string, string>  $alternates
     * @return array<string, string>
     */
    private function withDefault(array $alternates): array
    {
        if (count($alternates) < 2) {
            return [];
        }

        $default = Language::defaultCode();

        return isset($alternates[$default]) ? [...$alternates, 'x-default' => $alternates[$default]] : $alternates;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
