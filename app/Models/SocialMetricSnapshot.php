<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Metrics of one published variant as the platform reported them at captured_at. A null column means the platform
 * did not report that metric; values are never estimated.
 */
#[Fillable(['social_post_variant_id', 'impressions', 'engagements', 'clicks', 'raw', 'captured_at'])]
class SocialMetricSnapshot extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'impressions' => 'integer',
            'engagements' => 'integer',
            'clicks' => 'integer',
            'raw' => 'array',
            'captured_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<SocialPostVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(SocialPostVariant::class, 'social_post_variant_id');
    }
}
