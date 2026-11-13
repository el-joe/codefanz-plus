<?php

namespace App\Models;

use App\Enums\RadarConnectorType;
use App\Enums\RadarPlatformStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A freelance marketplace the radar watches. `status` is the honest connector status (recomputed from saved
 * credentials and the last run); `is_enabled` is the per-platform kill switch.
 */
#[Fillable(['key', 'name', 'connector', 'status', 'status_note', 'website_url', 'is_enabled', 'fetch_interval_minutes', 'rate_limit_per_hour', 'last_run_at', 'last_success_at', 'last_error', 'last_run_count'])]
class RadarPlatform extends Model
{
    public const UPWORK = 'upwork';

    public const FREELANCER = 'freelancer';

    public const MOSTAQL = 'mostaql';

    public const PEOPLEPERHOUR = 'peopleperhour';

    public const KHAMSAT = 'khamsat';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'connector' => RadarConnectorType::class,
            'status' => RadarPlatformStatus::class,
            'is_enabled' => 'boolean',
            'fetch_interval_minutes' => 'integer',
            'rate_limit_per_hour' => 'integer',
            'last_run_at' => 'datetime',
            'last_success_at' => 'datetime',
            'last_run_count' => 'integer',
        ];
    }

    /**
     * @return HasMany<RadarProject, $this>
     */
    public function projects(): HasMany
    {
        return $this->hasMany(RadarProject::class);
    }

    public function isDue(): bool
    {
        return $this->last_run_at === null || $this->last_run_at->addMinutes(max(5, $this->fetch_interval_minutes))->isPast();
    }

    public function displayStatus(): RadarPlatformStatus
    {
        return $this->is_enabled ? $this->status : RadarPlatformStatus::Paused;
    }
}
