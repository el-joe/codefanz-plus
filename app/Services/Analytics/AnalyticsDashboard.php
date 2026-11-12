<?php

namespace App\Services\Analytics;

use App\Enums\LeadSource;
use App\Models\AnalyticsSnapshot;
use App\Models\Lead;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * View data for the A13 dashboard, built only from cached snapshots and the CRM's own leads. When a source is not
 * configured or its connection failed, the state says so and the last cached numbers (if any) are shown with their
 * date — nothing is estimated or invented.
 */
class AnalyticsDashboard
{
    public const RANGES = [7, 28];

    public function __construct(private GoogleServiceAccount $google, private AnalyticsSync $sync) {}

    /**
     * @return array<string, mixed>
     */
    public function build(int $days): array
    {
        $days = in_array($days, self::RANGES, true) ? $days : 28;

        return [
            'days' => $days,
            'serviceAccountEmail' => $this->google->clientEmail(),
            'ga4' => $this->ga4($days),
            'gsc' => $this->searchConsole($days),
            'leads' => $this->leads($days),
        ];
    }

    /**
     * not_configured | pending | connected | reconnect | error
     */
    public function state(string $source): string
    {
        $configured = $this->google->isConfigured() && ($source === AnalyticsSnapshot::SOURCE_GA4 ? $this->sync->ga4PropertyId() : $this->sync->searchConsoleSite()) !== null;

        if (! $configured) {
            return 'not_configured';
        }

        return match ($this->sync->status($source)['state'] ?? null) {
            'ok' => 'connected',
            'reconnect' => 'reconnect',
            'error' => 'error',
            default => 'pending',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function ga4(int $days): array
    {
        $daily = $this->snapshot(AnalyticsSnapshot::SOURCE_GA4, 'daily');
        $rows = collect($daily?->values ?? [])->sortBy('date')->values();
        [$current, $previous] = $this->periods($rows, $days);

        return [
            'state' => $this->state(AnalyticsSnapshot::SOURCE_GA4),
            'status' => $this->sync->status(AnalyticsSnapshot::SOURCE_GA4),
            'fetchedAt' => $daily?->fetched_at,
            'hasData' => $rows->isNotEmpty(),
            'kpis' => collect(['activeUsers' => __('Users'), 'sessions' => __('Sessions'), 'screenPageViews' => __('Page views')])
                ->map(fn (string $label, string $metric): array => $this->kpi($label, $current->sum($metric), $previous->sum($metric), $previous->isNotEmpty()))
                ->all(),
            'chart' => [
                'labels' => $current->pluck('date')->map(fn (string $date): string => Carbon::parse($date)->translatedFormat('M j'))->all(),
                'series' => $current->pluck('sessions')->map(fn ($value): int => (int) $value)->all(),
            ],
            'channels' => $this->rows(AnalyticsSnapshot::SOURCE_GA4, 'channels', 'sessionDefaultChannelGroup', 'sessions'),
            'landingPages' => $this->rows(AnalyticsSnapshot::SOURCE_GA4, 'landing_pages', 'landingPage', 'sessions'),
            'events' => $this->rows(AnalyticsSnapshot::SOURCE_GA4, 'events', 'eventName', 'eventCount'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function searchConsole(int $days): array
    {
        $daily = $this->snapshot(AnalyticsSnapshot::SOURCE_GSC, 'daily');
        $rows = collect($daily?->values ?? [])->sortBy('key')->values()->map(fn (array $row): array => [...$row, 'date' => $row['key']]);
        [$current, $previous] = $this->periods($rows, $days);
        $ctr = fn (Collection $period): float => $period->sum('impressions') > 0 ? $period->sum('clicks') / $period->sum('impressions') * 100 : 0.0;
        $position = fn (Collection $period): float => $period->sum('impressions') > 0
            ? $period->sum(fn (array $row): float => $row['position'] * $row['impressions']) / $period->sum('impressions')
            : 0.0;

        return [
            'state' => $this->state(AnalyticsSnapshot::SOURCE_GSC),
            'status' => $this->sync->status(AnalyticsSnapshot::SOURCE_GSC),
            'fetchedAt' => $daily?->fetched_at,
            'hasData' => $rows->isNotEmpty(),
            'kpis' => [
                'clicks' => $this->kpi(__('Clicks'), $current->sum('clicks'), $previous->sum('clicks'), $previous->isNotEmpty()),
                'impressions' => $this->kpi(__('Impressions'), $current->sum('impressions'), $previous->sum('impressions'), $previous->isNotEmpty()),
                'ctr' => $this->kpi(__('CTR'), round($ctr($current), 1), round($ctr($previous), 1), $previous->isNotEmpty(), '%'),
                'position' => $this->kpi(__('Avg. position'), round($position($current), 1), round($position($previous), 1), $previous->isNotEmpty(), '', lowerIsBetter: true),
            ],
            'queries' => $this->rows(AnalyticsSnapshot::SOURCE_GSC, 'queries', 'key', 'clicks'),
            'pages' => $this->rows(AnalyticsSnapshot::SOURCE_GSC, 'pages', 'key', 'clicks'),
            'countries' => $this->rows(AnalyticsSnapshot::SOURCE_GSC, 'countries', 'key', 'clicks'),
            'devices' => $this->rows(AnalyticsSnapshot::SOURCE_GSC, 'devices', 'key', 'clicks'),
        ];
    }

    /**
     * Lead submissions from the CRM itself (always available, independent of Google).
     *
     * @return array<string, mixed>
     */
    private function leads(int $days): array
    {
        $start = today()->subDays($days - 1)->startOfDay();
        $previousStart = $start->copy()->subDays($days);
        $count = fn (Carbon $from, Carbon $to) => Lead::query()->whereBetween('created_at', [$from, $to])->toBase()->selectRaw('source, count(*) as total')->groupBy('source')->pluck('total', 'source');
        $current = $count($start, now());
        $previous = $count($previousStart, $start->copy()->subSecond());

        return [
            'kpi' => $this->kpi(__('Lead submissions'), (float) $current->sum(), (float) $previous->sum(), true),
            'bySource' => collect([LeadSource::QuoteForm, LeadSource::AiAssistant, LeadSource::ContactForm])
                ->map(fn (LeadSource $source): array => ['label' => $source->label(), 'value' => (int) ($current[$source->value] ?? 0)])
                ->all(),
        ];
    }

    private function snapshot(string $source, string $report): ?AnalyticsSnapshot
    {
        return AnalyticsSnapshot::query()->where('source', $source)->where('report_key', $report)->latest('date')->first();
    }

    /**
     * @return list<array{label: string, value: float}>
     */
    private function rows(string $source, string $report, string $labelKey, string $valueKey): array
    {
        return collect($this->snapshot($source, $report)?->values ?? [])
            ->map(fn (array $row): array => ['label' => (string) ($row[$labelKey] ?? ''), 'value' => (float) ($row[$valueKey] ?? 0)])
            ->values()
            ->all();
    }

    /**
     * The last $days rows and the $days before them.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array{0: Collection<int, array<string, mixed>>, 1: Collection<int, array<string, mixed>>}
     */
    private function periods(Collection $rows, int $days): array
    {
        $current = $rows->slice(-$days)->values();
        $previous = $rows->count() >= $days * 2 ? $rows->slice(-$days * 2, $days)->values() : collect();

        return [$current, $previous];
    }

    /**
     * @return array{label: string, value: float, change: float|null, suffix: string, lowerIsBetter: bool}
     */
    private function kpi(string $label, float|int $value, float|int $previous, bool $comparable, string $suffix = '', bool $lowerIsBetter = false): array
    {
        return [
            'label' => $label,
            'value' => (float) $value,
            'change' => $comparable && $previous > 0 ? round(($value - $previous) / $previous * 100, 1) : null,
            'suffix' => $suffix,
            'lowerIsBetter' => $lowerIsBetter,
        ];
    }
}
