<?php

namespace App\Actions\Radar;

use App\Events\RadarProjectScored;
use App\Models\RadarEvaluation;
use App\Models\RadarProject;
use App\Services\Radar\RadarScorer;
use App\Services\Radar\RadarSettings;

/**
 * Computes the rule score and stores it with its breakdown. Nothing is re-scored when neither the listing (content
 * hash) nor the weights changed, so the stored breakdown always explains the stored score.
 */
class ScoreRadarProject
{
    public function __construct(private RadarScorer $scorer, private RadarSettings $settings) {}

    public function handle(RadarProject $project, bool $force = false): RadarEvaluation
    {
        $weightsHash = $this->settings->weightsHash();
        $latest = $project->evaluations()->where('kind', RadarEvaluation::KIND_RULES)->latest('id')->first();

        if (! $force && $latest !== null && $latest->content_hash === $project->content_hash && $latest->weights_hash === $weightsHash) {
            return $latest;
        }

        $result = $this->scorer->score($project);

        $evaluation = $project->evaluations()->create([
            'kind' => RadarEvaluation::KIND_RULES,
            'score' => $result['score'],
            'breakdown' => $result['breakdown'],
            'content_hash' => $project->content_hash,
            'weights_hash' => $weightsHash,
            'created_at' => now(),
        ]);

        $project->forceFill(['score' => $result['score']])->save();

        RadarProjectScored::dispatch($project);

        return $evaluation;
    }
}
