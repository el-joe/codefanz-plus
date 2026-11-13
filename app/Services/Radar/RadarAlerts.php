<?php

namespace App\Services\Radar;

use App\Enums\KillSwitchKey;
use App\Enums\RadarAlertLevel;
use App\Exceptions\RadarConnectorException;
use App\Jobs\SendRadarAlert;
use App\Models\RadarAlert;
use App\Models\RadarFilter;
use App\Models\RadarProject;
use App\Services\KillSwitches;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Decides and sends Telegram alerts. Level comes from the rule score (configurable thresholds; the AI score never
 * changes it). One alert row per project, channel and content hash means an unchanged project never alerts twice.
 * When alerting filter profiles exist, only projects matching at least one of them alert.
 */
class RadarAlerts
{
    public function __construct(
        private RadarSettings $settings,
        private RadarFilterMatcher $matcher,
        private TelegramNotifier $telegram,
        private KillSwitches $killSwitches,
    ) {}

    public function level(int $score): ?RadarAlertLevel
    {
        $thresholds = $this->settings->thresholds();

        return match (true) {
            $score >= $thresholds['immediate'] => RadarAlertLevel::Immediate,
            $score >= $thresholds['normal'] => RadarAlertLevel::Normal,
            $score >= $thresholds['digest'] => RadarAlertLevel::Digest,
            default => null,
        };
    }

    public function consider(RadarProject $project): ?RadarAlert
    {
        if ($this->killSwitches->isActive(KillSwitchKey::RadarCollection)) {
            return null;
        }

        $level = $this->level((int) $project->score);

        if ($level === null || ! $this->passesAlertFilters($project)) {
            return null;
        }

        try {
            $alert = RadarAlert::query()->firstOrCreate(
                ['radar_project_id' => $project->id, 'channel' => RadarAlert::CHANNEL_TELEGRAM, 'content_hash' => $project->content_hash],
                ['level' => $level, 'score' => (int) $project->score, 'status' => RadarAlert::STATUS_PENDING],
            );
        } catch (UniqueConstraintViolationException) {
            return null;
        }

        if (! $alert->wasRecentlyCreated) {
            return null;
        }

        if ($level !== RadarAlertLevel::Digest) {
            if (! $this->telegram->isConfigured()) {
                $alert->forceFill(['status' => RadarAlert::STATUS_SKIPPED, 'error' => 'Telegram is not configured.'])->save();

                return $alert;
            }

            SendRadarAlert::dispatch($alert->id)->afterCommit();
        }

        return $alert;
    }

    public function sendNow(RadarAlert $alert): void
    {
        $alert->loadMissing('project.platform');

        if ($alert->status !== RadarAlert::STATUS_PENDING || $alert->project === null) {
            return;
        }

        try {
            $this->telegram->send($this->telegram->projectMessage($alert->project, $alert->level->label()));
            $alert->forceFill(['status' => RadarAlert::STATUS_SENT, 'sent_at' => now(), 'error' => null])->save();
        } catch (RadarConnectorException $exception) {
            $alert->forceFill(['status' => RadarAlert::STATUS_FAILED, 'error' => $exception->getMessage()])->save();
        }
    }

    /**
     * @return array{sent: bool, count: int, error: string|null}
     */
    public function sendDigest(): array
    {
        if ($this->killSwitches->isActive(KillSwitchKey::RadarCollection)) {
            return ['sent' => false, 'count' => 0, 'error' => 'Paused by an emergency control.'];
        }

        $pending = RadarAlert::query()
            ->where('channel', RadarAlert::CHANNEL_TELEGRAM)
            ->where('level', RadarAlertLevel::Digest)
            ->where('status', RadarAlert::STATUS_PENDING)
            ->whereHas('project')
            ->with('project.platform')
            ->orderByDesc('score')
            ->orderByDesc('id')
            ->get();

        if ($pending->isEmpty()) {
            return ['sent' => false, 'count' => 0, 'error' => null];
        }

        if (! $this->telegram->isConfigured()) {
            return ['sent' => false, 'count' => $pending->count(), 'error' => 'Telegram is not configured.'];
        }

        $top = $pending->take($this->settings->digestSize());

        try {
            $this->telegram->send($this->telegram->digestMessage($top->map(fn (RadarAlert $alert): RadarProject => $alert->project)->all(), $pending->count()));
        } catch (RadarConnectorException $exception) {
            return ['sent' => false, 'count' => $pending->count(), 'error' => $exception->getMessage()];
        }

        RadarAlert::query()->whereKey($top->modelKeys())->update(['status' => RadarAlert::STATUS_SENT, 'sent_at' => now()]);
        RadarAlert::query()->whereKey($pending->diff($top)->modelKeys())->update(['status' => RadarAlert::STATUS_SKIPPED, 'error' => 'Not in the digest top list.']);

        return ['sent' => true, 'count' => $top->count(), 'error' => null];
    }

    private function passesAlertFilters(RadarProject $project): bool
    {
        $filters = RadarFilter::query()->where('is_active', true)->where('alerts_enabled', true)->get();

        return $filters->isEmpty() || $filters->contains(fn (RadarFilter $filter): bool => $this->matcher->matches($filter, $project->loadMissing('platform')));
    }
}
