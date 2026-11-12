<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SocialPlatform;
use App\Http\Controllers\Controller;
use App\Jobs\FetchSocialMetrics;
use App\Services\AuditLogger;
use App\Services\Social\SocialMetrics;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Social metrics: totals and per-platform figures from the latest snapshot of each post, only as reported by the
 * platforms. Manual posts and platforms without an API show "not available" instead of a number.
 */
class SocialMetricsController extends Controller
{
    public function index(Request $request, SocialMetrics $metrics): View
    {
        $validated = $request->validate(['platform' => ['nullable', Rule::enum(SocialPlatform::class)]]);
        $platform = isset($validated['platform']) ? SocialPlatform::from($validated['platform']) : null;

        return view('admin.social.metrics', [
            'dashboard' => $metrics->dashboard($platform),
            'metrics' => $metrics,
            'platform' => $platform,
        ]);
    }

    public function refresh(Request $request, AuditLogger $audit): RedirectResponse
    {
        FetchSocialMetrics::dispatch();
        $audit->record(operation: 'social_metrics.refresh_requested', user: $request->user());

        return back()->with('toast', __('Metrics refresh queued. New numbers appear when the platforms answer.'));
    }
}
