<?php

namespace App\Http\Controllers;

use App\Enums\PageStatus;
use App\Models\PageTranslation;
use App\Models\PortfolioProjectTranslation;
use App\Models\PostTranslation;
use App\Models\ProductTranslation;
use App\Models\ServiceTranslation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Simple site search (used by the 404 page): titles of published services, public projects, products, articles and pages
 * in the current language. Results are not indexed (noindex).
 */
class SearchController extends Controller
{
    private const PER_TYPE = 8;

    public function __invoke(Request $request): View
    {
        $term = Str::of((string) $request->query('q'))->stripTags()->squish()->limit(80, '')->toString();
        $groups = mb_strlen($term) >= 2 ? $this->search($term, app()->getLocale()) : [];

        return view('search.index', ['term' => $term, 'groups' => array_filter($groups)]);
    }

    /**
     * @return array<string, list<array{title: string, summary: string|null, url: string}>>
     */
    private function search(string $term, string $locale): array
    {
        $like = '%'.addcslashes($term, '\\%_').'%';
        $matching = fn (Builder $query, string $column = 'title') => $query->where('locale', $locale)->where($column, 'like', $like)->limit(self::PER_TYPE);

        return [
            __('Services') => $matching(ServiceTranslation::query())
                ->whereHas('service', fn (Builder $query) => $query->published())
                ->get(['title', 'slug', 'summary'])
                ->map(fn (ServiceTranslation $row): array => ['title' => $row->title, 'summary' => $row->summary, 'url' => route('services.show', ['slug' => $row->slug])])->all(),
            __('Portfolio') => $matching(PortfolioProjectTranslation::query())
                ->whereHas('project', fn (Builder $query) => $query->publiclyListed())
                ->get(['title', 'slug', 'summary'])
                ->map(fn (PortfolioProjectTranslation $row): array => ['title' => $row->title, 'summary' => $row->summary, 'url' => route('portfolio.show', ['slug' => $row->slug])])->all(),
            __('Products') => $matching(ProductTranslation::query(), 'name')
                ->whereHas('product', fn (Builder $query) => $query->published())
                ->get(['name', 'slug', 'tagline'])
                ->map(fn (ProductTranslation $row): array => ['title' => $row->name, 'summary' => $row->tagline, 'url' => route('products.show', ['slug' => $row->slug])])->all(),
            __('Blog') => $matching(PostTranslation::query())
                ->whereHas('post', fn (Builder $query) => $query->published())
                ->get(['title', 'slug', 'excerpt'])
                ->map(fn (PostTranslation $row): array => ['title' => $row->title, 'summary' => $row->excerpt, 'url' => route('blog.show', ['slug' => $row->slug])])->all(),
            __('Pages') => $matching(PageTranslation::query())
                ->whereHas('page', fn (Builder $query) => $query->where('status', PageStatus::Published))
                ->get(['title', 'slug'])
                ->map(fn (PageTranslation $row): array => ['title' => $row->title, 'summary' => null, 'url' => route('pages.show', ['slug' => $row->slug])])->all(),
        ];
    }
}
