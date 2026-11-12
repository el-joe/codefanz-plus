<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Content\SaveIndustry;
use App\Enums\ContentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveIndustryRequest;
use App\Models\Industry;
use App\Models\Language;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class IndustryController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Industry::class);

        return view('admin.industries.index', [
            'industries' => Industry::query()->with('translations')->ordered()->paginate(30),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Industry::class);

        return view('admin.industries.form', ['industry' => new Industry]);
    }

    public function store(SaveIndustryRequest $request, SaveIndustry $save): RedirectResponse
    {
        $industry = $save->handle(null, $request->validated(), $request->user());

        return redirect()->route('admin.industries.edit', $industry)->with('toast', __('Industry created as a draft.'));
    }

    public function edit(Industry $industry): View
    {
        Gate::authorize('update', $industry);

        return view('admin.industries.form', ['industry' => $industry->load('translations')]);
    }

    public function update(SaveIndustryRequest $request, Industry $industry, SaveIndustry $save): RedirectResponse
    {
        $save->handle($industry, $request->validated(), $request->user());

        return redirect()->route('admin.industries.edit', $industry)->with('toast', __('Industry saved.'));
    }

    public function publish(Request $request, Industry $industry, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('publish', $industry);

        $publish = $industry->status !== ContentStatus::Published;

        if ($publish && ! $industry->translations()->where('locale', Language::defaultCode())->exists()) {
            return back()->withErrors(['status' => __('Add the default-language content before publishing.')]);
        }

        $industry->forceFill([
            'status' => $publish ? ContentStatus::Published : ContentStatus::Draft,
            'published_at' => $publish ? ($industry->published_at ?? now()) : $industry->published_at,
        ])->save();

        $audit->record(operation: $publish ? 'industry.published' : 'industry.unpublished', auditable: $industry, user: $request->user());

        return back()->with('toast', $publish ? __('Industry published.') : __('Industry moved back to draft.'));
    }

    public function destroy(Request $request, Industry $industry, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('delete', $industry);

        $industry->delete();
        $audit->record(operation: 'industry.deleted', auditable: $industry, user: $request->user());

        return redirect()->route('admin.industries.index')->with('toast', __('Industry moved to trash.'));
    }
}
