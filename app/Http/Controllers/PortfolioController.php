<?php

namespace App\Http\Controllers;

use App\Models\Industry;
use App\Models\PortfolioCategory;
use App\Models\PortfolioProject;
use App\Models\PortfolioProjectTranslation;
use App\Models\Technology;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PortfolioController extends Controller
{
    public const PER_PAGE = 9;

    /**
     * Relations every project card needs.
     *
     * @return array<int|string, mixed>
     */
    public static function cardRelations(): array
    {
        return [
            'translations',
            'industry' => fn ($query) => $query->published()->with('translations'),
            'technologies' => fn ($query) => $query->where('is_visible', true)->with('translations')->ordered(),
            'cover.variants',
        ];
    }

    public function index(Request $request): View
    {
        $locale = app()->getLocale();
        $search = Str::limit(trim((string) $request->query('q', '')), 100, '');
        $categorySlug = (string) $request->query('category', '');
        $industrySlug = (string) $request->query('industry', '');
        $technologyKey = (string) $request->query('technology', '');

        $listed = fn (): Builder => PortfolioProject::query()->publiclyListed()->translatedIn($locale);

        $projects = $listed()
            ->when($categorySlug !== '', fn (Builder $query) => $query->whereHas('category.translations', fn (Builder $translations) => $translations->where('locale', $locale)->where('slug', $categorySlug)))
            ->when($industrySlug !== '', fn (Builder $query) => $query->whereHas('industry', fn (Builder $industry) => $industry->published()->whereHas('translations', fn (Builder $translations) => $translations->where('locale', $locale)->where('slug', $industrySlug))))
            ->when($technologyKey !== '', fn (Builder $query) => $query->whereHas('technologies', fn (Builder $technology) => $technology->where('key', $technologyKey)->where('is_visible', true)))
            ->when($search !== '', function (Builder $query) use ($locale, $search): void {
                $like = '%'.addcslashes($search, '\\%_').'%';
                $query->whereHas('translations', fn (Builder $translations) => $translations->where('locale', $locale)
                    ->where(fn (Builder $match) => $match->where('title', 'like', $like)->orWhere('summary', 'like', $like)));
            });

        $isFiltered = $search !== '' || $categorySlug !== '' || $industrySlug !== '' || $technologyKey !== '';
        $featured = ! $isFiltered && $request->integer('page', 1) <= 1
            ? $listed()->where('is_featured', true)->with([...self::cardRelations(), 'category.translations'])->ordered()->first()
            : null;

        return view('portfolio.index', [
            'projects' => $projects
                ->when($featured, fn (Builder $query) => $query->whereKeyNot($featured->getKey()))
                ->with(self::cardRelations())
                ->ordered()
                ->paginate(self::PER_PAGE)
                ->withQueryString(),
            'featured' => $featured,
            'categories' => PortfolioCategory::query()
                ->translatedIn($locale)
                ->whereHas('projects', fn (Builder $query) => $query->publiclyListed()->translatedIn($locale))
                ->with('translations')
                ->ordered()
                ->get(),
            'industries' => Industry::query()
                ->published()
                ->translatedIn($locale)
                ->whereHas('portfolioProjects', fn (Builder $query) => $query->publiclyListed()->translatedIn($locale))
                ->with('translations')
                ->ordered()
                ->get(),
            'technology' => $technologyKey !== '' ? Technology::query()->where('key', $technologyKey)->where('is_visible', true)->with('translations')->first() : null,
            'filters' => ['q' => $search, 'category' => $categorySlug, 'industry' => $industrySlug, 'technology' => $technologyKey],
            'isFiltered' => $isFiltered,
        ]);
    }

    public function show(string $slug): View
    {
        $locale = app()->getLocale();

        $translation = PortfolioProjectTranslation::query()
            ->where('locale', $locale)
            ->where('slug', $slug)
            ->whereHas('project', fn (Builder $query) => $query->published())
            ->firstOrFail();

        $project = PortfolioProject::query()
            ->with([
                ...self::cardRelations(),
                'technologies' => fn ($query) => $query->where('is_visible', true)->withProficiency()->with('translations')->ordered(),
                'category.translations',
                'services' => fn ($query) => $query->published()->translatedIn($locale)->with('translations')->ordered(),
                'gallery.variants',
            ])
            ->findOrFail($translation->portfolio_project_id);

        $related = PortfolioProject::query()
            ->publiclyListed()
            ->translatedIn($locale)
            ->whereKeyNot($project->getKey())
            ->where(fn (Builder $query) => $query
                ->when($project->portfolio_category_id, fn (Builder $match) => $match->orWhere('portfolio_category_id', $project->portfolio_category_id))
                ->when($project->industry_id, fn (Builder $match) => $match->orWhere('industry_id', $project->industry_id))
                ->when(! $project->portfolio_category_id && ! $project->industry_id, fn (Builder $match) => $match->where('type', $project->type)))
            ->with(self::cardRelations())
            ->ordered()
            ->limit(3)
            ->get();

        $markdown = fn (?string $text): string => Str::markdown((string) $text, ['html_input' => 'strip', 'allow_unsafe_links' => false]);

        return view('portfolio.show', [
            'project' => $project,
            'translation' => $translation,
            'challenge' => $markdown($translation->challenge),
            'solution' => $markdown($translation->solution),
            'results' => $markdown($translation->results),
            'related' => $related,
        ]);
    }
}
