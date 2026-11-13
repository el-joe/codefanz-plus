<?php

namespace App\Jobs;

use App\Actions\Radar\ScoreRadarProjectWithAi as ScoreWithAi;
use App\Exceptions\AiUnavailable;
use App\Models\RadarProject;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Automatic AI score for projects above the digest threshold (when enabled on Radar → Settings). A paused or
 * unavailable AI simply leaves the project with its rule score.
 */
class ScoreRadarProjectWithAi implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $projectId) {}

    public function handle(ScoreWithAi $score): void
    {
        $project = RadarProject::query()->find($this->projectId);

        if ($project === null) {
            return;
        }

        try {
            $score->handle($project);
        } catch (AiUnavailable) {
            // Rule score stays the only score.
        }
    }
}
