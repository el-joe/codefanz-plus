<?php

namespace App\Actions\Radar;

use App\Enums\KillSwitchKey;
use App\Models\RadarPlatform;
use App\Services\KillSwitches;
use App\Services\Radar\RadarPlatforms;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Imports one saved-search alert email (Mostaql / PeoplePerHour) forwarded by n8n's IMAP trigger or a mail
 * provider's inbound hook. The sender domain (or the explicit platform key) picks the parser.
 */
class ImportRadarAlertEmail
{
    public function __construct(private RadarPlatforms $platforms, private IngestRadarProjects $ingest, private KillSwitches $killSwitches) {}

    /**
     * @param  array{platform?: string|null, from: string, subject?: string|null, html?: string|null, text?: string|null, received_at?: string|null}  $email
     * @return array{platform: string, parsed: int, created: int, updated: int, unchanged: int}
     */
    public function handle(array $email): array
    {
        if ($this->killSwitches->isActive(KillSwitchKey::RadarCollection)) {
            throw ValidationException::withMessages(['platform' => __('Freelance Radar collection is paused by an emergency control.')]);
        }

        $key = $this->platforms->emailPlatformKey($email['platform'] ?? null, $email['from']);
        $platform = $key !== null ? RadarPlatform::query()->where('key', $key)->first() : null;

        if ($platform === null) {
            throw ValidationException::withMessages(['from' => __('This email is not a supported platform alert.')]);
        }

        if (! $platform->is_enabled) {
            throw ValidationException::withMessages(['platform' => __(':platform is switched off in the radar.', ['platform' => $platform->name])]);
        }

        $receivedAt = filled($email['received_at'] ?? null) ? CarbonImmutable::parse((string) $email['received_at']) : CarbonImmutable::now();
        $items = $this->platforms->parser($platform->key)?->parse((string) ($email['subject'] ?? ''), $email['html'] ?? null, $email['text'] ?? null, $receivedAt) ?? [];
        $counts = $this->ingest->handle($platform, $items);

        $platform->forceFill(['last_run_at' => now(), 'last_success_at' => now(), 'last_run_count' => count($items), 'last_error' => null])->save();

        return ['platform' => $platform->key, 'parsed' => count($items), ...$counts];
    }
}
