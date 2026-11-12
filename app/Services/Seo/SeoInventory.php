<?php

namespace App\Services\Seo;

use App\Models\Language;
use App\Models\Post;
use App\Models\SeoMeta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Every indexable item of the public site per locale, with its effective SEO fields, indexability and score.
 * Feeds the admin SEO meta editor.
 */
class SeoInventory
{
    /**
     * Translation column used as the description fallback on the public page.
     *
     * @var array<string, string|null>
     */
    private const SUMMARY_FIELDS = [
        'page' => null,
        'service' => 'summary',
        'portfolio_project' => 'summary',
        'product' => 'tagline',
        'career' => 'summary',
        'post' => 'excerpt',
    ];

    public function __construct(private SeoTargets $targets, private SeoScore $score) {}

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function rows(?string $type = null, ?string $locale = null): Collection
    {
        $locales = $locale !== null ? [$locale] : Language::active()->keys()->all();
        $rows = collect();

        if ($type === null || $type === 'route') {
            $rows = $rows->concat($this->routeRows($locales));
        }

        foreach ($this->targets->types() as $entityType) {
            if ($type === null || $type === $entityType) {
                $rows = $rows->concat($this->entityRows($entityType, $locales));
            }
        }

        return $rows->values();
    }

    /**
     * @param  list<string>  $locales
     * @return Collection<int, array<string, mixed>>
     */
    private function routeRows(array $locales): Collection
    {
        $meta = SeoMeta::query()->whereNotNull('route_key')->get()->keyBy(fn (SeoMeta $row): string => $row->route_key.':'.$row->locale);

        return collect($this->targets->staticRoutes())->flatMap(function (string $label, string $routeName) use ($locales, $meta): array {
            return array_map(function (string $locale) use ($label, $routeName, $meta): array {
                /** @var SeoMeta|null $row */
                $row = $meta->get($routeName.':'.$locale);
                $noindex = (bool) $row?->noindex;
                $url = route($routeName, ['locale' => $locale]);
                $reason = $noindex ? __('Set to noindex') : null;
                $title = $row?->title ?: __($label, [], $locale);

                return $this->row([
                    'kind' => 'route',
                    'type' => 'route',
                    'type_label' => __('Site pages'),
                    'id' => null,
                    'route_key' => $routeName,
                    'locale' => $locale,
                    'name' => __($label),
                    'url' => $url,
                    'seo_title' => $row?->title,
                    'seo_description' => $row?->description,
                    'focus_keyword' => $row?->focus_keyword,
                    'canonical_url' => $row?->canonical_url,
                    'noindex' => $noindex,
                    'effective_title' => $title,
                    'effective_description' => $row?->description,
                    'reason' => $reason,
                    'version' => $row?->lock_version ?? 0,
                    'edit_url' => null,
                ]);
            }, $locales);
        });
    }

    /**
     * @param  list<string>  $locales
     * @return Collection<int, array<string, mixed>>
     */
    private function entityRows(string $type, array $locales): Collection
    {
        $modelClass = $this->targets->model($type);
        /** @var Model $prototype */
        $prototype = new $modelClass;
        $records = $modelClass::query()->with('translations')->orderByDesc('updated_at')->get();
        $meta = SeoMeta::query()
            ->where('seoable_type', $prototype->getMorphClass())
            ->whereIn('seoable_id', $records->modelKeys())
            ->get()
            ->keyBy(fn (SeoMeta $row): string => $row->seoable_id.':'.$row->locale);
        $titleField = $this->targets->titleField($type);
        $summaryField = self::SUMMARY_FIELDS[$type];

        return $records->flatMap(function (Model $record) use ($type, $locales, $meta, $titleField, $summaryField): array {
            $rows = [];

            foreach ($record->translations as $translation) {
                if (! in_array($translation->locale, $locales, true)) {
                    continue;
                }

                /** @var SeoMeta|null $row */
                $row = $meta->get($record->getKey().':'.$translation->locale);
                $isPost = $record instanceof Post;
                $canonical = $isPost ? $translation->canonical_url : $row?->canonical_url;
                $url = $this->targets->url($type, $translation->slug, $translation->locale);
                $name = (string) $translation->{$titleField};

                $rows[] = $this->row([
                    'kind' => 'entity',
                    'type' => $type,
                    'type_label' => $this->targets->label($type),
                    'id' => $record->getKey(),
                    'route_key' => null,
                    'locale' => $translation->locale,
                    'name' => $name,
                    'url' => $url,
                    'seo_title' => $translation->seo_title,
                    'seo_description' => $translation->seo_description,
                    'focus_keyword' => $isPost ? $translation->focus_keyword : $row?->focus_keyword,
                    'canonical_url' => $canonical,
                    'noindex' => (bool) $row?->noindex,
                    'effective_title' => $translation->seo_title ?: $name,
                    'effective_description' => $translation->seo_description ?: ($summaryField ? $translation->{$summaryField} : null),
                    'reason' => $this->targets->notIndexableReason($record, (bool) $row?->noindex, $canonical, $url),
                    'version' => (int) $record->lock_version,
                    'edit_url' => $this->targets->editUrl($type, $record),
                ]);
            }

            return $rows;
        });
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function row(array $row): array
    {
        $row['key'] = $row['type'].'-'.($row['id'] ?? str_replace('.', '-', (string) $row['route_key'])).'-'.$row['locale'];
        $row['indexable'] = $row['reason'] === null;
        $row['score'] = $this->score->evaluate([
            'title' => $row['effective_title'],
            'description' => $row['effective_description'],
            'focus_keyword' => $row['focus_keyword'],
            'indexable' => $row['indexable'],
        ]);

        return $row;
    }
}
