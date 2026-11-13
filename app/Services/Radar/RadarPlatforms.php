<?php

namespace App\Services\Radar;

use App\Contracts\RadarConnector;
use App\Enums\AutomationAction;
use App\Enums\AutomationDirection;
use App\Enums\RadarConnectorType;
use App\Enums\RadarPlatformStatus;
use App\Models\AutomationWorkflow;
use App\Models\RadarPlatform;
use App\Services\Radar\Connectors\FreelancerConnector;
use App\Services\Radar\Connectors\UpworkConnector;
use App\Services\Radar\Parsers\AlertEmailParser;
use App\Services\Radar\Parsers\MostaqlAlertParser;
use App\Services\Radar\Parsers\PeoplePerHourAlertParser;

/**
 * Connector registry and honest status: API platforms are Active only with saved credentials (and Error after a
 * failed run), email platforms only while an enabled inbound "Radar alert email" endpoint exists, and platforms
 * without a permitted integration stay Unavailable.
 */
class RadarPlatforms
{
    public function connector(RadarPlatform $platform): ?RadarConnector
    {
        return match ($platform->key) {
            RadarPlatform::FREELANCER => app(FreelancerConnector::class),
            RadarPlatform::UPWORK => app(UpworkConnector::class),
            default => null,
        };
    }

    public function parser(string $platformKey): ?AlertEmailParser
    {
        return match ($platformKey) {
            RadarPlatform::MOSTAQL => app(MostaqlAlertParser::class),
            RadarPlatform::PEOPLEPERHOUR => app(PeoplePerHourAlertParser::class),
            default => null,
        };
    }

    /**
     * The email platform an alert came from: the explicit key when given, otherwise the sender's domain.
     */
    public function emailPlatformKey(?string $explicit, string $from): ?string
    {
        if ($explicit !== null && $this->parser($explicit) !== null) {
            return $explicit;
        }

        $domain = mb_strtolower((string) substr((string) strrchr(trim($from, " <>\t"), '@'), 1));
        $domain = rtrim($domain, '>');

        foreach ([RadarPlatform::MOSTAQL, RadarPlatform::PEOPLEPERHOUR] as $key) {
            foreach ($this->parser($key)?->senderDomains() ?? [] as $platformDomain) {
                if ($domain === $platformDomain || str_ends_with($domain, '.'.$platformDomain)) {
                    return $key;
                }
            }
        }

        return null;
    }

    public function refreshStatuses(): void
    {
        $emailEndpoint = AutomationWorkflow::query()
            ->where('direction', AutomationDirection::Inbound)
            ->where('action', AutomationAction::RadarEmail)
            ->where('is_enabled', true)
            ->exists();

        foreach (RadarPlatform::query()->get() as $platform) {
            $status = match ($platform->connector) {
                RadarConnectorType::None => RadarPlatformStatus::Unavailable,
                RadarConnectorType::Email => $emailEndpoint ? RadarPlatformStatus::Active : RadarPlatformStatus::NeedsCredentials,
                RadarConnectorType::Api => match (true) {
                    ! ($this->connector($platform)?->isConfigured() ?? false) => RadarPlatformStatus::NeedsCredentials,
                    $platform->last_error !== null => RadarPlatformStatus::Error,
                    default => RadarPlatformStatus::Active,
                },
            };

            if ($platform->status !== $status) {
                $platform->forceFill(['status' => $status])->save();
            }
        }
    }
}
