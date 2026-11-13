<?php

namespace App\Models;

use App\Enums\RadarAlertLevel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One alert decision per project, channel and content hash (unique), so an unchanged project never alerts twice.
 */
#[Fillable(['radar_project_id', 'channel', 'level', 'content_hash', 'score', 'status', 'error', 'sent_at'])]
class RadarAlert extends Model
{
    public const CHANNEL_TELEGRAM = 'telegram';

    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => RadarAlertLevel::class,
            'score' => 'integer',
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<RadarProject, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(RadarProject::class, 'radar_project_id');
    }
}
