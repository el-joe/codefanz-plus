<?php

namespace App\Http\Controllers;

use App\Enums\PageSectionType;
use App\Enums\ServiceTier;
use App\Enums\TechnologyProficiency;
use App\Models\Faq;
use App\Models\Industry;
use App\Models\PageSection;
use App\Models\PortfolioProject;
use App\Models\Post;
use App\Models\Product;
use App\Models\Service;
use App\Models\Statistic;
use App\Models\Technology;
use App\Models\Testimonial;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Builds the homepage from the managed sections (order, visibility, copy) and live catalog data.
 * A list section without data is skipped, so the page never shows empty blocks or placeholder numbers.
 */
class HomeController extends Controller
{
    public function __invoke(): View
    {
        $sections = PageSection::query()->home()->with('translations')->ordered()->get();

        if ($sections->isEmpty()) {
            $sections = PageSection::defaultHomeSections();
        }

        $blocks = $sections
            ->filter(fn (PageSection $section): bool => $section->is_visible && $section->type->isAvailable())
            ->map(fn (PageSection $section): array => ['section' => $section, 'copy' => $section->copy(), 'data' => $this->data($section)])
            ->filter(fn (array $block): bool => $this->hasContent($block))
            ->values();

        return view('pages.home', ['blocks' => $blocks]);
    }

    /**
     * @param  array{section: PageSection, copy: array<string, mixed>, data: Collection<int, mixed>|null}  $block
     */
    private function hasContent(array $block): bool
    {
        return match ($block['section']->type) {
            PageSectionType::Hero, PageSectionType::Cta => true,
            PageSectionType::Process => $block['copy']['items'] !== [],
            PageSectionType::WhyUs => $block['copy']['items'] !== [] || $block['copy']['points'] !== [],
            default => $block['data'] !== null && $block['data']->isNotEmpty(),
        };
    }

    /**
     * @return Collection<int, mixed>|null
     */
    private function data(PageSection $section): ?Collection
    {
        $limit = $section->limit();

        return match ($section->type) {
            PageSectionType::Services => Service::query()
                ->published()->translatedIn()->whereNull('parent_id')
                ->with('translations')
                ->orderByDesc('is_featured')
                ->orderByRaw('tier = ? desc', [ServiceTier::Core->value])
                ->ordered()->limit($limit)->get(),
            PageSectionType::Technologies => Technology::query()
                ->where('is_visible', true)->translatedIn()->withProficiency()->with('translations')->ordered()->get()
                ->reject(fn (Technology $technology): bool => $technology->proficiency() === TechnologyProficiency::Supported)
                ->sortBy(fn (Technology $technology): int => $technology->proficiency() === TechnologyProficiency::VerifiedInProjects ? 0 : 1)
                ->take($limit)->values(),
            PageSectionType::FeaturedProjects => PortfolioProject::query()
                ->publiclyListed()->translatedIn()
                ->with(PortfolioController::cardRelations())
                ->orderByDesc('is_featured')->ordered()->limit($limit)->get(),
            PageSectionType::Products => Product::query()->published()->translatedIn()->with('translations')->ordered()->limit($limit)->get(),
            PageSectionType::Industries => Industry::query()->published()->translatedIn()->with('translations')->ordered()->limit($limit)->get(),
            PageSectionType::Statistics => Statistic::query()->published()->translatedIn()->with('translations')->ordered()->limit($limit)->get(),
            PageSectionType::Testimonials => Testimonial::query()
                ->publiclyVisible()->where('is_featured', true)->translatedIn()
                ->with(['translations', 'avatar.variants'])->ordered()->limit($limit)->get(),
            PageSectionType::LatestArticles => Post::query()->published()->translatedIn()->with(Post::cardRelations())->latestPublished()->limit($limit)->get(),
            PageSectionType::Faq => Faq::query()->published()->translatedIn()->with('translations')->orderByDesc('is_featured')->ordered()->limit($limit)->get(),
            default => null,
        };
    }
}
