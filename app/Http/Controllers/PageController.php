<?php

namespace App\Http\Controllers;

use App\Enums\PageStatus;
use App\Models\PageTranslation;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PageController extends Controller
{
    public function __invoke(string $slug): View
    {
        $translation = PageTranslation::query()
            ->where('locale', app()->getLocale())
            ->where('slug', $slug)
            ->whereHas('page', fn ($query) => $query->where('status', PageStatus::Published))
            ->with('page.translations')
            ->firstOrFail();

        return view('pages.show', [
            'translation' => $translation,
            'alternates' => $translation->page->translations,
            'body' => Str::markdown((string) $translation->body, [
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ]),
        ]);
    }
}
