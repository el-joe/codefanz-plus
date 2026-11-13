<?php

namespace App\Actions\Radar;

use App\Enums\AiFeature;
use App\Jobs\ScoreRadarProjectWithAi;
use App\Models\RadarPlatform;
use App\Models\RadarProject;
use App\Services\Ai\AiGateway;
use App\Services\Radar\RadarAlerts;
use App\Services\Radar\RadarProjectData;
use App\Services\Radar\RadarSettings;
use App\Services\Radar\RadarText;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * The collection pipeline for one batch from any connector or alert email: dedupe on (platform, external_id),
 * skip unchanged listings, store new / changed ones, score them, decide alerts and (optionally) queue the AI score.
 */
class IngestRadarProjects
{
    public function __construct(
        private ScoreRadarProject $score,
        private RadarAlerts $alerts,
        private RadarSettings $settings,
        private AiGateway $ai,
    ) {}

    /**
     * @param  list<RadarProjectData>  $items
     * @return array{created: int, updated: int, unchanged: int}
     */
    public function handle(RadarPlatform $platform, array $items): array
    {
        $counts = ['created' => 0, 'updated' => 0, 'unchanged' => 0];
        $seen = [];

        foreach ($items as $item) {
            if (isset($seen[$item->externalId])) {
                continue;
            }

            $seen[$item->externalId] = true;
            $hash = $item->contentHash();

            $project = RadarProject::withTrashed()
                ->where('radar_platform_id', $platform->id)
                ->where('external_id', $item->externalId)
                ->first();

            if ($project !== null && $project->content_hash === $hash) {
                $counts['unchanged']++;

                continue;
            }

            $isNew = $project === null;
            $project ??= new RadarProject(['radar_platform_id' => $platform->id, 'external_id' => $item->externalId]);

            $project->fill([
                'url' => mb_substr($item->url, 0, 500),
                'title' => mb_substr($item->title, 0, 255),
                'description' => $item->description,
                'budget_min_minor' => $item->budgetMinMinor,
                'budget_max_minor' => $item->budgetMaxMinor,
                'currency' => $item->currency,
                'budget_type' => $item->budgetType,
                'skills' => $item->skills ?: null,
                'client_country' => $item->clientCountry,
                'client_rating' => $item->clientRating,
                'client_spent_minor' => $item->clientSpentMinor,
                'client_payment_verified' => $item->clientPaymentVerified,
                'proposals_count' => $item->proposalsCount,
                'language' => RadarText::detectLanguage($item->title.' '.$item->description),
                'posted_at' => $item->postedAt,
                'collected_at' => now(),
                'content_hash' => $hash,
                'source' => $item->source,
            ]);

            try {
                $project->save();
            } catch (UniqueConstraintViolationException) {
                // Another run stored the same listing a moment ago.
                $counts['unchanged']++;

                continue;
            }

            $counts[$isNew ? 'created' : 'updated']++;
            $project->setRelation('platform', $platform);

            $this->score->handle($project);
            $this->alerts->consider($project);

            if ($this->settings->aiAutoScore()
                && $project->score >= $this->settings->thresholds()['digest']
                && $this->ai->unavailableReason(AiFeature::Radar) === null) {
                ScoreRadarProjectWithAi::dispatch($project->id)->afterCommit();
            }
        }

        return $counts;
    }
}
