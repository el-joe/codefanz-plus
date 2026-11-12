<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Content\SaveCareer;
use App\Enums\ContentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveCareerRequest;
use App\Models\Career;
use App\Models\Language;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CareerController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Career::class);

        return view('admin.careers.index', ['careers' => Career::query()->with('translations')->ordered()->paginate(30)]);
    }

    public function create(): View
    {
        Gate::authorize('create', Career::class);

        return view('admin.careers.form', ['career' => new Career]);
    }

    public function store(SaveCareerRequest $request, SaveCareer $save): RedirectResponse
    {
        $career = $save->handle(null, $request->validated(), $request->user());

        return redirect()->route('admin.careers.edit', $career)->with('toast', __('Position created as a draft.'));
    }

    public function edit(Career $career): View
    {
        Gate::authorize('update', $career);

        return view('admin.careers.form', ['career' => $career->load('translations')]);
    }

    public function update(SaveCareerRequest $request, Career $career, SaveCareer $save): RedirectResponse
    {
        $save->handle($career, $request->validated(), $request->user());

        return redirect()->route('admin.careers.edit', $career)->with('toast', __('Position saved.'));
    }

    public function publish(Request $request, Career $career, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('publish', $career);

        $publish = $career->status !== ContentStatus::Published;

        if ($publish && ! $career->translations()->where('locale', Language::defaultCode())->exists()) {
            return back()->withErrors(['status' => __('Add the default-language content before publishing.')]);
        }

        $career->forceFill([
            'status' => $publish ? ContentStatus::Published : ContentStatus::Draft,
            'published_at' => $publish ? ($career->published_at ?? now()) : $career->published_at,
        ])->save();

        $audit->record(operation: $publish ? 'career.published' : 'career.unpublished', auditable: $career, user: $request->user());

        return back()->with('toast', $publish ? __('Position published.') : __('Position moved back to draft.'));
    }

    public function destroy(Request $request, Career $career, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('delete', $career);

        $career->delete();
        $audit->record(operation: 'career.deleted', auditable: $career, user: $request->user());

        return redirect()->route('admin.careers.index')->with('toast', __('Position moved to trash.'));
    }
}
