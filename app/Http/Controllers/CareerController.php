<?php

namespace App\Http\Controllers;

use App\Enums\PageSectionType;
use App\Models\Career;
use App\Models\CareerTranslation;
use App\Models\PageSection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CareerController extends Controller
{
    /**
     * Open positions plus the "Why work with us" perks (Admin → Page sections → Careers page), shown once filled.
     */
    public function index(): View
    {
        $perks = PageSection::visibleFor(PageSection::CAREERS)->get(PageSectionType::CareersPerks->value)?->copy();

        return view('careers.index', [
            'careers' => Career::query()->open()->translatedIn()->with('translations')->ordered()->get(),
            'perks' => $perks !== null && $perks['items'] !== [] ? $perks : null,
        ]);
    }

    /**
     * Drafts, trashed and closed positions are not found.
     */
    public function show(string $slug): View
    {
        $translation = CareerTranslation::query()
            ->where('locale', app()->getLocale())
            ->where('slug', $slug)
            ->whereHas('career', fn (Builder $query) => $query->open())
            ->firstOrFail();

        $career = Career::query()->with('translations')->findOrFail($translation->career_id);

        return view('careers.show', [
            'career' => $career,
            'translation' => $translation,
            'body' => Str::markdown((string) $translation->body, ['html_input' => 'strip', 'allow_unsafe_links' => false]),
        ]);
    }
}
