<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Content\SaveFaq;
use App\Enums\ContentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveFaqRequest;
use App\Models\Faq;
use App\Models\FaqCategory;
use App\Models\Language;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class FaqController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Faq::class);

        return view('admin.faqs.index', [
            'faqs' => Faq::query()->with(['translations', 'category.translations'])->ordered()->paginate(50),
            'categories' => FaqCategory::query()->with('translations')->withCount('faqs')->ordered()->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Faq::class);

        return view('admin.faqs.form', ['faq' => new Faq, 'categories' => $this->categories()]);
    }

    public function store(SaveFaqRequest $request, SaveFaq $save): RedirectResponse
    {
        $faq = $save->handle(null, $request->validated(), $request->user());

        return redirect()->route('admin.faqs.edit', $faq)->with('toast', __('Question created as a draft.'));
    }

    public function edit(Faq $faq): View
    {
        Gate::authorize('update', $faq);

        return view('admin.faqs.form', ['faq' => $faq->load('translations'), 'categories' => $this->categories()]);
    }

    public function update(SaveFaqRequest $request, Faq $faq, SaveFaq $save): RedirectResponse
    {
        $save->handle($faq, $request->validated(), $request->user());

        return redirect()->route('admin.faqs.edit', $faq)->with('toast', __('Question saved.'));
    }

    public function publish(Request $request, Faq $faq, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('publish', $faq);

        $publish = $faq->status !== ContentStatus::Published;

        if ($publish && ! $faq->translations()->where('locale', Language::defaultCode())->exists()) {
            return back()->withErrors(['status' => __('Add the default-language content before publishing.')]);
        }

        $faq->forceFill([
            'status' => $publish ? ContentStatus::Published : ContentStatus::Draft,
            'published_at' => $publish ? ($faq->published_at ?? now()) : $faq->published_at,
        ])->save();

        $audit->record(operation: $publish ? 'faq.published' : 'faq.unpublished', auditable: $faq, user: $request->user());

        return back()->with('toast', $publish ? __('Question published.') : __('Question moved back to draft.'));
    }

    public function destroy(Request $request, Faq $faq, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('delete', $faq);

        $faq->delete();
        $audit->record(operation: 'faq.deleted', auditable: $faq, user: $request->user());

        return redirect()->route('admin.faqs.index')->with('toast', __('Question moved to trash.'));
    }

    /**
     * @return Collection<int, FaqCategory>
     */
    private function categories(): Collection
    {
        return FaqCategory::query()->with('translations')->ordered()->get();
    }
}
