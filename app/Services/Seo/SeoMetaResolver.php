<?php

namespace App\Services\Seo;

use App\Models\SeoMeta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Reads the SEO editor's per-locale settings for the public layout. Fixed site pages are cached as one plain
 * array (flushed whenever a seo_meta row changes); content rows are a single indexed lookup.
 */
class SeoMetaResolver
{
    private const ROUTES_CACHE_KEY = 'seo:route-meta';

    /**
     * @return array{title: ?string, description: ?string, focus_keyword: ?string, canonical_url: ?string, noindex: bool}|null
     */
    public function forRoute(string $routeName, string $locale): ?array
    {
        $map = Cache::memo()->rememberForever(self::ROUTES_CACHE_KEY, fn (): array => SeoMeta::query()
            ->whereNotNull('route_key')
            ->get(['route_key', 'locale', 'title', 'description', 'focus_keyword', 'canonical_url', 'noindex'])
            ->groupBy('route_key')
            ->map(fn ($rows): array => $rows->keyBy('locale')->map(fn (SeoMeta $meta): array => $this->fields($meta))->all())
            ->all());

        return $map[$routeName][$locale] ?? null;
    }

    /**
     * @return array{title: ?string, description: ?string, focus_keyword: ?string, canonical_url: ?string, noindex: bool}|null
     */
    public function forModel(Model $model, string $locale): ?array
    {
        $meta = SeoMeta::query()
            ->where('seoable_type', $model->getMorphClass())
            ->where('seoable_id', $model->getKey())
            ->where('locale', $locale)
            ->first();

        return $meta ? $this->fields($meta) : null;
    }

    public static function flush(): void
    {
        Cache::memo()->forget(self::ROUTES_CACHE_KEY);
    }

    /**
     * @return array{title: ?string, description: ?string, focus_keyword: ?string, canonical_url: ?string, noindex: bool}
     */
    private function fields(SeoMeta $meta): array
    {
        return [
            'title' => $meta->title,
            'description' => $meta->description,
            'focus_keyword' => $meta->focus_keyword,
            'canonical_url' => $meta->canonical_url,
            'noindex' => (bool) $meta->noindex,
        ];
    }
}
