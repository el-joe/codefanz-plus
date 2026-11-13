<?php

namespace App\Services\Radar;

use App\Enums\LeadStatus;
use App\Enums\RadarProjectStatus;
use App\Models\RadarPlatform;
use App\Models\RadarProject;
use Carbon\CarbonImmutable;

/**
 * Radar dashboard numbers over a window of days: new projects per day and per platform, status and score-band
 * counts, conversion to leads / won, and the most requested skills. Plain arrays only (cache-safe).
 */
class RadarAnalytics
{
    public function __construct(private RadarSettings $settings) {}

    /**
     * @return array<string, mixed>
     */
    public function summary(int $days = 30): array
    {
        $from = CarbonImmutable::today()->subDays($days - 1);
        $window = fn () => RadarProject::query()->where('created_at', '>=', $from);
        $thresholds = $this->settings->thresholds();

        $daily = $window()
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day')
            ->all();

        $labels = [];
        $series = [];

        for ($date = $from; $date->lte(CarbonImmutable::today()); $date = $date->addDay()) {
            $labels[] = $date->translatedFormat('M j');
            $series[] = (int) ($daily[$date->toDateString()] ?? 0);
        }

        $platformNames = RadarPlatform::query()->pluck('name', 'id');
        $byPlatform = $window()->selectRaw('radar_platform_id, COUNT(*) as total')->groupBy('radar_platform_id')->pluck('total', 'radar_platform_id')
            ->mapWithKeys(fn ($total, $id): array => [(string) ($platformNames[$id] ?? $id) => (int) $total])
            ->sortDesc()
            ->all();

        $statusCounts = $window()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status')->all();
        $byStatus = collect(RadarProjectStatus::cases())->mapWithKeys(fn (RadarProjectStatus $status): array => [$status->value => (int) ($statusCounts[$status->value] ?? 0)])->all();

        $bands = $window()->selectRaw(
            'SUM(CASE WHEN score >= ? THEN 1 ELSE 0 END) as immediate, SUM(CASE WHEN score >= ? AND score < ? THEN 1 ELSE 0 END) as normal, SUM(CASE WHEN score >= ? AND score < ? THEN 1 ELSE 0 END) as digest, SUM(CASE WHEN score < ? OR score IS NULL THEN 1 ELSE 0 END) as saved',
            [$thresholds['immediate'], $thresholds['normal'], $thresholds['immediate'], $thresholds['digest'], $thresholds['normal'], $thresholds['digest']],
        )->first();

        $skills = [];

        foreach ($window()->whereNotNull('skills')->latest('id')->limit(2000)->pluck('skills') as $list) {
            foreach ((array) $list as $skill) {
                $skill = trim((string) $skill);

                if ($skill !== '') {
                    $skills[$skill] = ($skills[$skill] ?? 0) + 1;
                }
            }
        }

        arsort($skills);

        $converted = $window()->whereNotNull('lead_id');

        return [
            'days' => $days,
            'total' => array_sum($series),
            'chart' => ['labels' => $labels, 'series' => $series],
            'by_platform' => $byPlatform,
            'by_status' => $byStatus,
            'bands' => [
                'immediate' => (int) ($bands->immediate ?? 0),
                'normal' => (int) ($bands->normal ?? 0),
                'digest' => (int) ($bands->digest ?? 0),
                'saved' => (int) ($bands->saved ?? 0),
            ],
            'thresholds' => $thresholds,
            'top_skills' => array_slice($skills, 0, 10, true),
            'converted' => (clone $converted)->count(),
            'converted_won' => (clone $converted)->whereHas('lead', fn ($query) => $query->where('status', LeadStatus::Won))->count(),
            'won' => (int) ($statusCounts[RadarProjectStatus::Won->value] ?? 0),
            'applied' => (int) ($statusCounts[RadarProjectStatus::Applied->value] ?? 0),
        ];
    }

    /**
     * Header tiles for the radar list (B09).
     *
     * @return array{new_today: int, top_matches: int, applied_month: int, won_month: int}
     */
    public function tiles(): array
    {
        $monthStart = CarbonImmutable::now()->startOfMonth();

        return [
            'new_today' => RadarProject::query()->where('created_at', '>=', CarbonImmutable::today())->count(),
            'top_matches' => RadarProject::query()->where('created_at', '>=', CarbonImmutable::today()->subDays(6))->where('score', '>=', $this->settings->thresholds()['immediate'])->count(),
            'applied_month' => RadarProject::query()->where('status', RadarProjectStatus::Applied)->where('updated_at', '>=', $monthStart)->count(),
            'won_month' => RadarProject::query()->where('status', RadarProjectStatus::Won)->where('updated_at', '>=', $monthStart)->count(),
        ];
    }
}
