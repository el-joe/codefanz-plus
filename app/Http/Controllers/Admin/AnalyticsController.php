<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Analytics\AnalyticsDashboard;
use App\Services\Analytics\AnalyticsSync;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * A13 GA4 + Search Console dashboard (seo.manage). Shows cached snapshots only; "Refresh now" runs the same
 * pull as the daily schedule.
 */
class AnalyticsController extends Controller
{
    public function index(Request $request, AnalyticsDashboard $dashboard): View
    {
        return view('admin.analytics.index', $dashboard->build((int) $request->query('days', 28)));
    }

    public function sync(Request $request, AnalyticsSync $sync, AuditLogger $audit): RedirectResponse
    {
        $result = $sync->run();
        $audit->record(operation: 'analytics.synced', user: $request->user(), after: collect($result)->map(fn (string $outcome): string => mb_substr($outcome, 0, 120))->all());

        $ok = collect($result)->contains('ok');

        return back()->with('toast', $ok ? __('Analytics refreshed from Google.') : __('Nothing was refreshed. Check the connection status below.'));
    }
}
