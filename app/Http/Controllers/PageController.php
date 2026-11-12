<?php

namespace App\Http\Controllers;

use App\Enums\PageStatus;
use App\Models\PageTranslation;
use App\Services\LegalDocument;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PageController extends Controller
{
    public function __invoke(string $slug, LegalDocument $legal): View
    {
        $translation = PageTranslation::query()
            ->where('locale', app()->getLocale())
            ->where('slug', $slug)
            ->whereHas('page', fn ($query) => $query->where('status', PageStatus::Published))
            ->with('page.translations')
            ->firstOrFail();

        if ($translation->page->template === 'legal') {
            $document = $legal->render((string) $translation->body);

            return view('pages.legal', [
                'translation' => $translation,
                'alternates' => $translation->page->translations,
                'body' => $document['html'],
                'toc' => $document['toc'],
            ]);
        }

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
