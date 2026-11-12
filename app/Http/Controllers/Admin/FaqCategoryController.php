<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Content\SaveFaqCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveFaqCategoryRequest;
use App\Models\FaqCategory;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class FaqCategoryController extends Controller
{
    public function create(): View
    {
        Gate::authorize('create', FaqCategory::class);

        return view('admin.faqs.category-form', ['category' => new FaqCategory]);
    }

    public function store(SaveFaqCategoryRequest $request, SaveFaqCategory $save): RedirectResponse
    {
        $save->handle(null, $request->validated(), $request->user());

        return redirect()->route('admin.faqs.index')->with('toast', __('Category created.'));
    }

    public function edit(FaqCategory $faqCategory): View
    {
        Gate::authorize('update', $faqCategory);

        return view('admin.faqs.category-form', ['category' => $faqCategory->load('translations')]);
    }

    public function update(SaveFaqCategoryRequest $request, FaqCategory $faqCategory, SaveFaqCategory $save): RedirectResponse
    {
        $save->handle($faqCategory, $request->validated(), $request->user());

        return redirect()->route('admin.faqs.index')->with('toast', __('Category saved.'));
    }

    public function destroy(Request $request, FaqCategory $faqCategory, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('delete', $faqCategory);

        $audit->record(operation: 'faq_category.deleted', auditable: $faqCategory, user: $request->user(), before: ['translations' => $faqCategory->translationSnapshot(FaqCategory::TRANSLATED_FIELDS)]);
        $faqCategory->delete();

        return redirect()->route('admin.faqs.index')->with('toast', __('Category deleted. Its questions are now uncategorized.'));
    }
}
