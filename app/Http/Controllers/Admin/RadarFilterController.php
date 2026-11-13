<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveRadarFilterRequest;
use App\Models\Language;
use App\Models\RadarFilter;
use App\Models\RadarPlatform;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Saved filter profiles (radar.manage). They are list tabs and the Telegram alert gate; they never delete projects.
 */
class RadarFilterController extends Controller
{
    public function create(): View
    {
        return view('admin.radar.filter-form', ['filter' => new RadarFilter(['rules' => []]), ...$this->formData()]);
    }

    public function store(SaveRadarFilterRequest $request, AuditLogger $audit): RedirectResponse
    {
        $filter = RadarFilter::create([...$request->filterData(), 'created_by' => $request->user()->getKey(), 'updated_by' => $request->user()->getKey()]);

        $audit->record(operation: 'radar.filter_created', auditable: $filter, user: $request->user(), after: $filter->only(['name', 'rules', 'is_active', 'alerts_enabled']));

        return to_route('admin.radar.settings')->with('toast', __('Filter profile saved.'));
    }

    public function edit(RadarFilter $filter): View
    {
        return view('admin.radar.filter-form', ['filter' => $filter, ...$this->formData()]);
    }

    public function update(SaveRadarFilterRequest $request, RadarFilter $filter, AuditLogger $audit): RedirectResponse
    {
        $before = $filter->only(['name', 'rules', 'is_active', 'alerts_enabled']);
        $filter->fill([...$request->filterData(), 'updated_by' => $request->user()->getKey()])->save();

        $audit->record(operation: 'radar.filter_updated', auditable: $filter, user: $request->user(), before: $before, after: $filter->only(['name', 'rules', 'is_active', 'alerts_enabled']));

        return to_route('admin.radar.settings')->with('toast', __('Filter profile saved.'));
    }

    public function destroy(Request $request, RadarFilter $filter, AuditLogger $audit): RedirectResponse
    {
        $filter->delete();

        $audit->record(operation: 'radar.filter_deleted', auditable: $filter, user: $request->user(), before: $filter->only(['name', 'rules']));

        return to_route('admin.radar.settings')->with('toast', __('Filter profile deleted.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            'platforms' => RadarPlatform::query()->orderBy('id')->pluck('name', 'key')->all(),
            'languages' => Language::active()->map(fn (array $language): string => $language['name'])->all(),
        ];
    }
}
