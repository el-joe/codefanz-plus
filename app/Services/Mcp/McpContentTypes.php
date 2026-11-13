<?php

namespace App\Services\Mcp;

use App\Contracts\Approvable;
use App\Models\Page;
use App\Models\PortfolioProject;
use App\Models\Post;
use App\Models\Product;
use App\Models\Service;
use App\Services\Seo\SeoTargets;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Website content the MCP API can read and propose changes to (keyed by morph alias). Every type implements
 * Approvable, so MCP can only ever change it through a pending change request.
 */
class McpContentTypes
{
    public const TYPES = ['page', 'service', 'portfolio_project', 'product', 'post'];

    /**
     * @var array<string, array{model: class-string<Model&Approvable>, title: string, summary: string|null}>
     */
    private const DEFINITIONS = [
        'page' => ['model' => Page::class, 'title' => 'title', 'summary' => null],
        'service' => ['model' => Service::class, 'title' => 'title', 'summary' => 'summary'],
        'portfolio_project' => ['model' => PortfolioProject::class, 'title' => 'title', 'summary' => 'summary'],
        'product' => ['model' => Product::class, 'title' => 'name', 'summary' => 'tagline'],
        'post' => ['model' => Post::class, 'title' => 'title', 'summary' => 'excerpt'],
    ];

    public function __construct(private SeoTargets $seoTargets) {}

    public function titleField(string $type): string
    {
        return self::DEFINITIONS[$type]['title'];
    }

    public function summaryField(string $type): ?string
    {
        return self::DEFINITIONS[$type]['summary'];
    }

    /**
     * @param  'published'|'draft'|'all'  $visibility
     * @return Builder<Model>
     */
    public function query(string $type, string $visibility = 'published'): Builder
    {
        $model = self::DEFINITIONS[$type]['model'];

        return match ($visibility) {
            'all' => $model::query(),
            'draft' => $model::query()->whereNotIn((new $model)->getQualifiedKeyName(), $this->seoTargets->publicQuery($type)->select((new $model)->getQualifiedKeyName())),
            default => $this->seoTargets->publicQuery($type),
        };
    }

    /**
     * Find one item by numeric id or by slug (in the given locale, or any locale).
     */
    public function find(string $type, string $key, bool $includeDrafts, ?string $locale = null): (Model&Approvable)|null
    {
        $query = $this->query($type, $includeDrafts ? 'all' : 'published')->with('translations');

        /** @var (Model&Approvable)|null */
        return ctype_digit($key)
            ? $query->whereKey((int) $key)->first()
            : $query->whereHas('translations', fn (Builder $translations) => $translations
                ->where('slug', $key)
                ->when($locale, fn (Builder $scoped) => $scoped->where('locale', $locale)))->first();
    }

    public function isPublic(Model $model): bool
    {
        return $this->seoTargets->isPublic($model);
    }

    public function publicUrl(string $type, Model $model, string $locale): ?string
    {
        $slug = $model->translations->firstWhere('locale', $locale)?->slug;

        return $slug && $this->isPublic($model) ? $this->seoTargets->url($type, $slug, $locale) : null;
    }
}
