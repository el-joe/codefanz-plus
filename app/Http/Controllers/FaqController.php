<?php

namespace App\Http\Controllers;

use App\Models\Faq;
use App\Models\FaqCategory;
use Illuminate\View\View;

class FaqController extends Controller
{
    public function __invoke(): View
    {
        $faqs = Faq::query()->published()->translatedIn()->with('translations')->ordered()->get();

        $categories = FaqCategory::query()
            ->whereIn('id', $faqs->pluck('faq_category_id')->filter()->unique())
            ->with('translations')
            ->ordered()
            ->get();

        $groups = $categories
            ->map(fn (FaqCategory $category): array => ['category' => $category, 'faqs' => $faqs->where('faq_category_id', $category->id)->values()])
            ->push(['category' => null, 'faqs' => $faqs->filter(fn (Faq $faq): bool => ! $categories->contains('id', $faq->faq_category_id))->values()])
            ->filter(fn (array $group): bool => $group['faqs']->isNotEmpty())
            ->values();

        return view('faq.index', ['groups' => $groups, 'faqs' => $faqs]);
    }
}
