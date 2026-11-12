<?php

namespace App\Http\Controllers;

use App\Enums\PageSectionType;
use App\Models\PageSection;
use App\Models\Statistic;
use Illuminate\View\View;

/**
 * About page built from the managed "about" sections. Story, values and disciplines only show once an
 * admin has written them, so a fresh install never shows invented facts.
 */
class AboutController extends Controller
{
    public function __invoke(): View
    {
        $sections = PageSection::visibleFor(PageSection::ABOUT);
        $copy = $sections->map(fn (PageSection $section): array => $section->copy());

        $blocks = $sections
            ->reject(fn (PageSection $section): bool => $section->type === PageSectionType::AboutIntro)
            ->filter(fn (PageSection $section): bool => match ($section->type) {
                PageSectionType::AboutStory => filled($copy[$section->type->value]['subheading']),
                PageSectionType::AboutValues, PageSectionType::AboutTeam => $copy[$section->type->value]['items'] !== [],
                default => true,
            })
            ->map(fn (PageSection $section): array => ['section' => $section, 'copy' => $copy[$section->type->value]])
            ->values();

        return view('about.index', [
            // A hidden introduction still needs the page title, so it falls back to the default wording.
            'intro' => $copy[PageSectionType::AboutIntro->value] ?? PageSectionType::AboutIntro->defaults(),
            'blocks' => $blocks,
            'statistics' => $blocks->contains(fn (array $block): bool => $block['section']->type === PageSectionType::AboutStory)
                ? Statistic::query()->published()->translatedIn()->with('translations')->ordered()->limit(2)->get()
                : collect(),
        ]);
    }
}
