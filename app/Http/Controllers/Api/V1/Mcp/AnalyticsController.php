<?php

namespace App\Http\Controllers\Api\V1\Mcp;

use App\Services\Analytics\AnalyticsDashboard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * get_analytics_summary: the A13 numbers built from cached GA4 / Search Console snapshots and the CRM's own
 * leads. Google is never called from here; a source that is not configured says so instead of inventing data.
 */
class AnalyticsController extends McpController
{
    private const TOP_ROWS = 10;

    public function __invoke(Request $request, AnalyticsDashboard $dashboard): JsonResponse
    {
        $validated = $request->validate(['days' => ['nullable', 'integer', Rule::in(AnalyticsDashboard::RANGES)]]);
        $data = $dashboard->build((int) ($validated['days'] ?? 28));

        $kpis = fn (array $kpis): array => collect($kpis)->map(fn (array $kpi): array => [
            'label' => $kpi['label'],
            'value' => $kpi['value'],
            'change_percent' => $kpi['change'],
        ])->all();
        $top = fn (array $rows): array => array_slice($rows, 0, self::TOP_ROWS);

        return new JsonResponse(['data' => [
            'days' => $data['days'],
            'ga4' => [
                'state' => $data['ga4']['state'],
                'fetched_at' => $data['ga4']['fetchedAt']?->toIso8601String(),
                'has_data' => $data['ga4']['hasData'],
                'kpis' => $kpis($data['ga4']['kpis']),
                'channels' => $top($data['ga4']['channels']),
                'landing_pages' => $top($data['ga4']['landingPages']),
            ],
            'search_console' => [
                'state' => $data['gsc']['state'],
                'fetched_at' => $data['gsc']['fetchedAt']?->toIso8601String(),
                'has_data' => $data['gsc']['hasData'],
                'kpis' => $kpis($data['gsc']['kpis']),
                'queries' => $top($data['gsc']['queries']),
                'pages' => $top($data['gsc']['pages']),
            ],
            'leads' => [
                'submissions' => $data['leads']['kpi']['value'],
                'change_percent' => $data['leads']['kpi']['change'],
                'by_source' => $data['leads']['bySource'],
            ],
        ]]);
    }
}
