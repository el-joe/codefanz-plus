<?php

namespace App\Services\Radar;

use App\Enums\RadarBudgetType;
use App\Models\RadarProject;
use Carbon\CarbonInterface;

/**
 * Transparent rule score (0–100): six factors, each scored 0–100 with a reason, combined as a weighted average
 * using the weights from Radar → Settings. The same project, weights and clock always give the same result. A low
 * score never hides or deletes a project; it only decides the alert level.
 */
class RadarScorer
{
    public function __construct(private RadarSettings $settings) {}

    /**
     * @return array{score: int, breakdown: list<array{factor: string, score: int, weight: int, reason: string, params: array<string, string|int>}>}
     */
    public function score(RadarProject $project, ?CarbonInterface $now = null): array
    {
        $now ??= now();
        $weights = $this->settings->weights();

        $factors = [
            'skills' => $this->skills($project),
            'budget' => $this->budget($project),
            'client' => $this->client($project),
            'freshness' => $this->freshness($project, $now),
            'competition' => $this->competition($project),
            'language' => $this->language($project),
        ];

        $breakdown = [];
        $weighted = 0;

        foreach ($factors as $factor => [$score, $reason, $params]) {
            $weight = $weights[$factor] ?? 0;
            $weighted += $score * $weight;
            $breakdown[] = ['factor' => $factor, 'score' => $score, 'weight' => $weight, 'reason' => $reason, 'params' => $params];
        }

        $totalWeight = array_sum($weights);

        return [
            'score' => $totalWeight > 0 ? (int) round($weighted / $totalWeight) : 0,
            'breakdown' => $breakdown,
        ];
    }

    /**
     * Our skills found in the project's skills (or, without a skill list, mentioned in the title / description).
     *
     * @return list<string>
     */
    public function matchingSkills(RadarProject $project): array
    {
        $ours = $this->settings->ourSkills();
        $requested = $project->skillList();

        if ($requested !== []) {
            return array_values(array_filter($requested, fn (string $skill): bool => collect($ours)->contains(
                fn (string $our): bool => RadarText::containsTerm($skill, $our) || RadarText::containsTerm($our, $skill),
            )));
        }

        $text = $project->title."\n".$project->description;

        return array_values(array_filter($ours, fn (string $our): bool => RadarText::containsTerm($text, $our)));
    }

    /**
     * @return array{0: int, 1: string, 2: array<string, string|int>}
     */
    private function skills(RadarProject $project): array
    {
        $requested = $project->skillList();
        $matched = $this->matchingSkills($project);
        $list = implode(', ', array_slice($matched, 0, 6));

        if ($requested !== []) {
            if ($matched === []) {
                $mentioned = array_values(array_filter($this->settings->ourSkills(), fn (string $our): bool => RadarText::containsTerm($project->title."\n".$project->description, $our)));

                return $mentioned === []
                    ? [0, 'None of the :total requested skills match our technologies', ['total' => count($requested)]]
                    : [min(60, 20 + 10 * count($mentioned)), 'Requested skills differ, but the description mentions :skills', ['skills' => implode(', ', array_slice($mentioned, 0, 6))]];
            }

            return [(int) round(count($matched) / count($requested) * 100), 'Matches :skills (:matched of :total requested skills)', ['skills' => $list, 'matched' => count($matched), 'total' => count($requested)]];
        }

        return $matched === []
            ? [0, 'No skill list and none of our technologies mentioned', []]
            : [min(100, 25 * count($matched)), 'No skill list; the text mentions :skills', ['skills' => $list]];
    }

    /**
     * @return array{0: int, 1: string, 2: array<string, string|int>}
     */
    private function budget(RadarProject $project): array
    {
        $amount = $project->budget_max_minor ?? $project->budget_min_minor;

        if ($amount === null || $project->currency === null) {
            return [50, 'Budget not stated', []];
        }

        $rate = config('codefanz.radar.usd_rates.'.$project->currency);

        if (! is_numeric($rate)) {
            return [50, 'Budget in :currency (no conversion rate configured)', ['currency' => $project->currency]];
        }

        $usd = (int) round($amount / 100 * (float) $rate);
        $hourly = $project->budget_type === RadarBudgetType::Hourly;
        $target = $hourly ? $this->settings->targetHourlyUsd() : $this->settings->targetBudgetUsd();
        $score = max(0, min(100, (int) round($usd / $target * 100)));

        return [$score, $hourly ? 'About :usd USD per hour against a :target USD target' : 'About :usd USD against a :target USD target', ['usd' => $usd, 'target' => $target]];
    }

    /**
     * @return array{0: int, 1: string, 2: array<string, string|int>}
     */
    private function client(RadarProject $project): array
    {
        $parts = [];
        $params = [];

        if ($project->client_rating !== null) {
            $parts[] = min(100, (int) round($project->client_rating / 5 * 100));
            $params['rating'] = number_format($project->client_rating, 1);
        }

        if ($project->client_payment_verified !== null) {
            $parts[] = $project->client_payment_verified ? 100 : 20;
        }

        if ($project->client_spent_minor !== null) {
            $parts[] = min(100, (int) round($project->client_spent_minor / 100 / 5000 * 100));
        }

        if ($parts === []) {
            return [50, 'No client information in the listing', []];
        }

        $reason = match (true) {
            $project->client_payment_verified === true && isset($params['rating']) => 'Payment verified, rating :rating',
            $project->client_payment_verified === true => 'Payment verified',
            $project->client_payment_verified === false => 'Payment not verified',
            isset($params['rating']) => 'Client rating :rating',
            default => 'Client spending history available',
        };

        return [(int) round(array_sum($parts) / count($parts)), $reason, $params];
    }

    /**
     * @return array{0: int, 1: string, 2: array<string, string|int>}
     */
    private function freshness(RadarProject $project, CarbonInterface $now): array
    {
        $posted = $project->posted_at ?? $project->collected_at ?? $now;
        $hours = max(0, (int) floor($posted->diffInMinutes($now, true) / 60));

        $score = match (true) {
            $hours < 1 => 100,
            $hours < 6 => 90,
            $hours < 24 => 70,
            $hours < 72 => 40,
            default => 15,
        };

        return [$score, 'Posted :hours hours ago', ['hours' => $hours]];
    }

    /**
     * @return array{0: int, 1: string, 2: array<string, string|int>}
     */
    private function competition(RadarProject $project): array
    {
        $count = $project->proposals_count;

        if ($count === null) {
            return [50, 'Proposal count not available', []];
        }

        $score = match (true) {
            $count <= 5 => 100,
            $count <= 10 => 85,
            $count <= 20 => 65,
            $count <= 40 => 40,
            default => 15,
        };

        return [$score, ':count proposals so far', ['count' => $count]];
    }

    /**
     * @return array{0: int, 1: string, 2: array<string, string|int>}
     */
    private function language(RadarProject $project): array
    {
        return in_array($project->language, $this->settings->languages(), true)
            ? [100, 'Written in a language we work in (:language)', ['language' => $project->language]]
            : [20, 'Written in :language, which is not one of our working languages', ['language' => $project->language]];
    }
}
