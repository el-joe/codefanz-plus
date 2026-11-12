<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Content\SaveStatistic;
use App\Enums\ContentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveStatisticRequest;
use App\Models\Language;
use App\Models\Statistic;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class StatisticController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Statistic::class);

        return view('admin.statistics.index', ['statistics' => Statistic::query()->with('translations')->ordered()->get()]);
    }

    public function create(): View
    {
        Gate::authorize('create', Statistic::class);

        return view('admin.statistics.form', ['statistic' => new Statistic]);
    }

    public function store(SaveStatisticRequest $request, SaveStatistic $save): RedirectResponse
    {
        $statistic = $save->handle(null, $request->validated(), $request->user());

        return redirect()->route('admin.statistics.edit', $statistic)->with('toast', __('Statistic created as a draft.'));
    }

    public function edit(Statistic $statistic): View
    {
        Gate::authorize('update', $statistic);

        return view('admin.statistics.form', ['statistic' => $statistic->load('translations')]);
    }

    public function update(SaveStatisticRequest $request, Statistic $statistic, SaveStatistic $save): RedirectResponse
    {
        $save->handle($statistic, $request->validated(), $request->user());

        return redirect()->route('admin.statistics.edit', $statistic)->with('toast', __('Statistic saved.'));
    }

    public function publish(Request $request, Statistic $statistic, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('publish', $statistic);

        $publish = $statistic->status !== ContentStatus::Published;

        if ($publish && ! $statistic->translations()->where('locale', Language::defaultCode())->exists()) {
            return back()->withErrors(['status' => __('Add the default-language content before publishing.')]);
        }

        $statistic->forceFill(['status' => $publish ? ContentStatus::Published : ContentStatus::Draft])->save();
        $audit->record(operation: $publish ? 'statistic.published' : 'statistic.unpublished', auditable: $statistic, user: $request->user());

        return back()->with('toast', $publish ? __('Statistic published.') : __('Statistic moved back to draft.'));
    }

    public function destroy(Request $request, Statistic $statistic, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('delete', $statistic);

        $audit->record(operation: 'statistic.deleted', auditable: $statistic, user: $request->user(), before: ['value' => (string) $statistic->value, 'translations' => $statistic->translationSnapshot(Statistic::TRANSLATED_FIELDS)]);
        $statistic->delete();

        return redirect()->route('admin.statistics.index')->with('toast', __('Statistic deleted.'));
    }
}
