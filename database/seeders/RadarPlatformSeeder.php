<?php

namespace Database\Seeders;

use App\Enums\RadarConnectorType;
use App\Enums\RadarPlatformStatus;
use App\Models\RadarPlatform;
use App\Services\Radar\RadarPlatforms;
use Illuminate\Database\Seeder;

/**
 * Radar platforms with their honest connector status (docs/IMPLEMENTATION_ROADMAP.md §2). Collection is limited
 * to official APIs and alert emails the platform sends us; nothing is scraped. Existing rows keep admin changes.
 */
class RadarPlatformSeeder extends Seeder
{
    public function run(): void
    {
        $platforms = [
            [
                'key' => RadarPlatform::UPWORK, 'name' => 'Upwork', 'connector' => RadarConnectorType::Api,
                'status' => RadarPlatformStatus::NeedsCredentials, 'website_url' => 'https://www.upwork.com',
                'status_note' => 'Official GraphQL API. Needs an approved Upwork API key (client credentials) in Settings → Freelance Radar.',
                'fetch_interval_minutes' => 30, 'rate_limit_per_hour' => 20,
            ],
            [
                'key' => RadarPlatform::FREELANCER, 'name' => 'Freelancer', 'connector' => RadarConnectorType::Api,
                'status' => RadarPlatformStatus::NeedsCredentials, 'website_url' => 'https://www.freelancer.com',
                'status_note' => 'Official public API (active projects search). Active once an API token is saved in Settings → Freelance Radar.',
                'fetch_interval_minutes' => 15, 'rate_limit_per_hour' => 30,
            ],
            [
                'key' => RadarPlatform::MOSTAQL, 'name' => 'Mostaql', 'connector' => RadarConnectorType::Email,
                'status' => RadarPlatformStatus::NeedsCredentials, 'website_url' => 'https://mostaql.com',
                'status_note' => 'No public API. Saved-search alert emails are forwarded by n8n to an inbound “Radar alert email” endpoint.',
                'fetch_interval_minutes' => 15, 'rate_limit_per_hour' => 120,
            ],
            [
                'key' => RadarPlatform::PEOPLEPERHOUR, 'name' => 'PeoplePerHour', 'connector' => RadarConnectorType::Email,
                'status' => RadarPlatformStatus::NeedsCredentials, 'website_url' => 'https://www.peopleperhour.com',
                'status_note' => 'Job alert emails are forwarded by n8n to an inbound “Radar alert email” endpoint.',
                'fetch_interval_minutes' => 15, 'rate_limit_per_hour' => 120,
            ],
            [
                'key' => RadarPlatform::KHAMSAT, 'name' => 'Khamsat', 'connector' => RadarConnectorType::None,
                'status' => RadarPlatformStatus::Unavailable, 'website_url' => 'https://khamsat.com',
                'status_note' => 'Mainly a services marketplace with no official API, RSS feed or alert email for buyer requests. Not collected.',
                'fetch_interval_minutes' => 60, 'rate_limit_per_hour' => 0,
            ],
        ];

        foreach ($platforms as $platform) {
            $model = RadarPlatform::query()->firstOrCreate(['key' => $platform['key']], $platform);

            // Connector facts are system data and follow the code; the switch, cadence and limit stay as configured.
            $model->fill(collect($platform)->only(['name', 'connector', 'status_note', 'website_url'])->all())->save();
        }

        app(RadarPlatforms::class)->refreshStatuses();
    }
}
