<?php

namespace App\Http\Controllers;

use App\Models\Career;
use App\Models\CareerTranslation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CareerController extends Controller
{
    public function index(): View
    {
        return view('careers.index', [
            'careers' => Career::query()->open()->translatedIn()->with('translations')->ordered()->get(),
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
