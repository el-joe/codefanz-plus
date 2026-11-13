<?php

namespace App\Services\Radar;

use App\Models\Technology;
use App\Models\TechnologyTranslation;
use App\Models\User;
use App\Services\Settings;

/**
 * Radar configuration edited on Radar → Settings (radar.manage): scoring weights, alert thresholds, our extra
 * keywords, budget targets, preferred languages and the digest size. Stored in the settings table (group
 * "radar_scoring"); defaults come from config/codefanz.php.
 */
class RadarSettings
{
    public const GROUP = 'radar_scoring';

    public const FACTORS = ['skills', 'budget', 'client', 'freshness', 'competition', 'language'];

    /**
     * @var list<string>|null
     */
    private ?array $ourSkills = null;

    public function __construct(private Settings $settings) {}

    /**
     * @return array<string, int>
     */
    public function weights(): array
    {
        $stored = (array) $this->settings->get(self::GROUP, 'weights', []);
        $defaults = (array) config('codefanz.radar.weights');

        return collect(self::FACTORS)->mapWithKeys(fn (string $factor): array => [
            $factor => max(0, min(100, (int) ($stored[$factor] ?? $defaults[$factor] ?? 0))),
        ])->all();
    }

    /**
     * @return array{immediate: int, normal: int, digest: int}
     */
    public function thresholds(): array
    {
        $stored = (array) $this->settings->get(self::GROUP, 'thresholds', []);
        $defaults = (array) config('codefanz.radar.thresholds');

        return [
            'immediate' => (int) ($stored['immediate'] ?? $defaults['immediate']),
            'normal' => (int) ($stored['normal'] ?? $defaults['normal']),
            'digest' => (int) ($stored['digest'] ?? $defaults['digest']),
        ];
    }

    /**
     * @return list<string>
     */
    public function keywords(): array
    {
        return array_values(array_filter(array_map('trim', (array) $this->settings->get(self::GROUP, 'keywords', [])), 'filled'));
    }

    public function targetBudgetUsd(): int
    {
        return max(1, (int) $this->settings->get(self::GROUP, 'target_budget_usd', config('codefanz.radar.target_budget_usd')));
    }

    public function targetHourlyUsd(): int
    {
        return max(1, (int) $this->settings->get(self::GROUP, 'target_hourly_usd', config('codefanz.radar.target_hourly_usd')));
    }

    /**
     * @return list<string>
     */
    public function languages(): array
    {
        return array_values((array) $this->settings->get(self::GROUP, 'languages', config('codefanz.radar.languages')));
    }

    public function digestSize(): int
    {
        return max(1, min(50, (int) $this->settings->get(self::GROUP, 'digest_size', config('codefanz.radar.digest_size'))));
    }

    public function aiAutoScore(): bool
    {
        return (bool) $this->settings->get(self::GROUP, 'ai_auto_score', false);
    }

    /**
     * Identifies the weight configuration a rule score was computed with.
     */
    public function weightsHash(): string
    {
        return sha1((string) json_encode([$this->weights(), $this->keywords(), $this->targetBudgetUsd(), $this->targetHourlyUsd(), $this->languages()]));
    }

    /**
     * What we can deliver: every technology name (all languages) plus the extra keywords.
     *
     * @return list<string>
     */
    public function ourSkills(): array
    {
        return $this->ourSkills ??= array_values(array_unique(array_filter([
            ...TechnologyTranslation::query()
                ->whereIn('technology_id', Technology::query()->select('id'))
                ->pluck('name')
                ->map(fn ($name): string => trim((string) $name))
                ->all(),
            ...$this->keywords(),
        ], fn (string $skill): bool => mb_strlen($skill) >= 2)));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function save(array $data, User $user): void
    {
        foreach (['weights', 'thresholds', 'keywords', 'target_budget_usd', 'target_hourly_usd', 'languages', 'digest_size', 'ai_auto_score'] as $key) {
            if (array_key_exists($key, $data)) {
                $this->settings->set(self::GROUP, $key, $data[$key], user: $user);
            }
        }

        $this->ourSkills = null;
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        return [
            'weights' => $this->weights(),
            'thresholds' => $this->thresholds(),
            'keywords' => $this->keywords(),
            'target_budget_usd' => $this->targetBudgetUsd(),
            'target_hourly_usd' => $this->targetHourlyUsd(),
            'languages' => $this->languages(),
            'digest_size' => $this->digestSize(),
            'ai_auto_score' => $this->aiAutoScore(),
        ];
    }
}
