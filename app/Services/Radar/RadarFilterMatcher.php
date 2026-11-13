<?php

namespace App\Services\Radar;

use App\Models\RadarFilter;
use App\Models\RadarPlatform;
use App\Models\RadarProject;
use Illuminate\Database\Eloquent\Builder;

/**
 * Evaluates a saved filter profile against a project. Every rule left empty matches everything; keywords and
 * skills match whole words, case-insensitively, in the title, description and skill list.
 */
class RadarFilterMatcher
{
    public function matches(RadarFilter $filter, RadarProject $project): bool
    {
        $text = $project->title."\n".$project->description."\n".implode(', ', $project->skillList());

        $include = $filter->ruleList('include_keywords');

        if ($include !== [] && ! collect($include)->contains(fn (string $keyword): bool => RadarText::containsTerm($text, $keyword))) {
            return false;
        }

        if (collect($filter->ruleList('exclude_keywords'))->contains(fn (string $keyword): bool => RadarText::containsTerm($text, $keyword))) {
            return false;
        }

        $skills = $filter->ruleList('skills');

        if ($skills !== [] && ! collect($skills)->contains(fn (string $skill): bool => RadarText::containsTerm($text, $skill))) {
            return false;
        }

        $platforms = $filter->ruleList('platforms');

        if ($platforms !== [] && ! in_array($project->platform?->key, $platforms, true)) {
            return false;
        }

        $languages = $filter->ruleList('languages');

        if ($languages !== [] && ! in_array($project->language, $languages, true)) {
            return false;
        }

        $countries = array_map('strtoupper', $filter->ruleList('countries'));

        if ($countries !== [] && ! in_array($project->client_country, $countries, true)) {
            return false;
        }

        $minScore = $filter->ruleNumber('min_score');

        if ($minScore !== null && ($project->score ?? 0) < $minScore) {
            return false;
        }

        $minBudget = $filter->ruleNumber('min_budget');

        if ($minBudget !== null && $minBudget > 0) {
            $amount = $project->budget_max_minor ?? $project->budget_min_minor;
            $rate = config('codefanz.radar.usd_rates.'.$project->currency);

            // Unknown budgets pass: a filter never hides a project just because the listing omits its budget.
            if ($amount !== null && is_numeric($rate) && $amount / 100 * (float) $rate < $minBudget) {
                return false;
            }
        }

        $budgetType = $filter->rules['budget_type'] ?? null;

        return ! (is_string($budgetType) && $budgetType !== '' && $project->budget_type->value !== $budgetType);
    }

    /**
     * The same profile as SQL for the radar list (substring matching; alerts use the exact whole-word matcher above).
     *
     * @param  Builder<RadarProject>  $query
     */
    public function apply(Builder $query, RadarFilter $filter): void
    {
        $like = fn (string $term): string => '%'.addcslashes($term, '%_\\').'%';
        $matchesText = function (Builder $query, string $term) use ($like): void {
            $query->where(fn (Builder $inner) => $inner->where('title', 'like', $like($term))->orWhere('description', 'like', $like($term))->orWhere('skills', 'like', $like($term)));
        };

        if (($include = $filter->ruleList('include_keywords')) !== []) {
            $query->where(function (Builder $inner) use ($include, $matchesText): void {
                foreach ($include as $term) {
                    $inner->orWhere(fn (Builder $one) => $matchesText($one, $term));
                }
            });
        }

        foreach ($filter->ruleList('exclude_keywords') as $term) {
            $query->whereNot(fn (Builder $one) => $matchesText($one, $term));
        }

        if (($skills = $filter->ruleList('skills')) !== []) {
            $query->where(function (Builder $inner) use ($skills, $matchesText): void {
                foreach ($skills as $term) {
                    $inner->orWhere(fn (Builder $one) => $matchesText($one, $term));
                }
            });
        }

        if (($platforms = $filter->ruleList('platforms')) !== []) {
            $query->whereIn('radar_platform_id', RadarPlatform::query()->whereIn('key', $platforms)->select('id'));
        }

        if (($languages = $filter->ruleList('languages')) !== []) {
            $query->whereIn('language', $languages);
        }

        if (($countries = array_map('strtoupper', $filter->ruleList('countries'))) !== []) {
            $query->whereIn('client_country', $countries);
        }

        if (($minScore = $filter->ruleNumber('min_score')) !== null) {
            $query->where('score', '>=', $minScore);
        }

        $budgetType = $filter->rules['budget_type'] ?? null;

        if (is_string($budgetType) && $budgetType !== '') {
            $query->where('budget_type', $budgetType);
        }
    }
}
