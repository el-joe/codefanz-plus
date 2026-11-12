<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Content\SavePage;
use App\Enums\ChangeRequestStatus;
use App\Enums\PageStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SavePageRequest;
use App\Models\ChangeRequest;
use App\Models\Language;
use App\Models\Page;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PageController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Page::class);

        return view('admin.pages.index', [
            'pages' => Page::query()->with('translations')->orderByDesc('updated_at')->orderByDesc('id')->paginate(20),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Page::class);

        return view('admin.pages.form', ['page' => new Page(['template' => 'default']), 'pendingChanges' => collect()]);
    }

    public function store(SavePageRequest $request, SavePage $save): RedirectResponse
    {
        $page = $save->handle(null, $request->validated(), $request->user());

        return redirect()->route('admin.pages.edit', $page)->with('toast', __('Page created as a draft.'));
    }

    public function edit(Page $page): View
    {
        Gate::authorize('update', $page);

        $page->load('translations');

        return view('admin.pages.form', [
            'page' => $page,
            'pendingChanges' => ChangeRequest::query()
                ->whereMorphedTo('target', $page)
                ->whereIn('status', [ChangeRequestStatus::Pending, ChangeRequestStatus::UnderReview, ChangeRequestStatus::Approved])
                ->latest('id')
                ->get(),
        ]);
    }

    public function update(SavePageRequest $request, Page $page, SavePage $save): RedirectResponse
    {
        $save->handle($page, $request->validated(), $request->user());

        return redirect()->route('admin.pages.edit', $page)->with('toast', __('Page saved.'));
    }

    public function publish(Request $request, Page $page, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('publish', $page);

        $publish = $page->status !== PageStatus::Published;

        if ($publish && ! $page->translations()->where('locale', Language::defaultCode())->exists()) {
            return back()->withErrors(['status' => __('Add the default-language content before publishing.')]);
        }

        $page->forceFill([
            'status' => $publish ? PageStatus::Published : PageStatus::Draft,
            'published_at' => $publish ? ($page->published_at ?? now()) : $page->published_at,
        ])->save();

        $audit->record(operation: $publish ? 'page.published' : 'page.unpublished', auditable: $page, user: $request->user());

        return back()->with('toast', $publish ? __('Page published.') : __('Page moved back to draft.'));
    }

    public function destroy(Request $request, Page $page, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('delete', $page);

        $page->delete();
        $audit->record(operation: 'page.deleted', auditable: $page, user: $request->user());

        return redirect()->route('admin.pages.index')->with('toast', __('Page moved to trash.'));
    }
}
