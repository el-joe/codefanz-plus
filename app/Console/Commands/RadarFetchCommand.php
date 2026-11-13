<?php

namespace App\Console\Commands;

use App\Actions\Radar\FetchRadarPlatform;
use App\Enums\RadarConnectorType;
use App\Models\RadarPlatform;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('radar:fetch {platform? : Platform key (default: every API platform that is due)} {--force : Ignore the platform cadence}')]
#[Description('Collect new projects from the radar platforms with an official API (per-platform cadence, rate limit and switch)')]
class RadarFetchCommand extends Command
{
    public function handle(FetchRadarPlatform $fetch): int
    {
        $platforms = RadarPlatform::query()
            ->where('connector', RadarConnectorType::Api)
            ->when($this->argument('platform'), fn ($query, $key) => $query->where('key', $key))
            ->orderBy('id')
            ->get();

        foreach ($platforms as $platform) {
            $result = $fetch->handle($platform, (bool) $this->option('force'));
            $this->line($platform->key.': '.json_encode($result));
        }

        return self::SUCCESS;
    }
}
