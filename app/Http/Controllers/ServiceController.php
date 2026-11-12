<?php

namespace App\Http\Controllers;

use App\Enums\ServiceTier;
use App\Models\PortfolioProject;
use App\Models\Service;
use App\Models\ServiceTranslation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ServiceController extends Controller
{
    public function index(): View
    {
        $services = Service::query()
            ->published()
            ->whereNull('parent_id')
            ->translatedIn()
            ->with('translations')
            ->ordered()
            ->get()
            ->groupBy(fn (Service $service): string => $service->tier->value);

        return view('services.index', [
            'core' => $services->get(ServiceTier::Core->value, collect()),
            'specialist' => $services->get(ServiceTier::Specialist->value, collect()),
        ]);
    }

    public function show(string $slug): View
    {
        $locale = app()->getLocale();

        $translation = ServiceTranslation::query()
            ->where('locale', $locale)
            ->where('slug', $slug)
            ->whereHas('service', fn (Builder $query) => $query->published())
            ->firstOrFail();

        $service = Service::query()
            ->with([
                'translations',
                'parent' => fn ($query) => $query->published()->with('translations'),
                'children' => fn ($query) => $query->published()->translatedIn($locale)->with('translations')->ordered(),
                'features.translations',
                'faqs.translations',
                'technologies' => fn ($query) => $query->where('is_visible', true)->withProficiency()->with('translations')->ordered(),
                'cover.variants',
            ])
            ->findOrFail($translation->service_id);

        return view('services.show', [
            'service' => $service,
            'translation' => $translation,
            'projects' => PortfolioProject::query()
                ->publiclyListed()
                ->translatedIn($locale)
                ->whereHas('services', fn (Builder $query) => $query->whereKey($service->getKey()))
                ->with(PortfolioController::cardRelations())
                ->ordered()
                ->limit(3)
                ->get(),
            'body' => Str::markdown((string) $translation->body, [
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ]),
        ]);
    }
}
