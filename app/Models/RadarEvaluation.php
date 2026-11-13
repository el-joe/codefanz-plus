<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One score for a radar project: the transparent rule score (breakdown per factor with its weight and reason) or
 * the optional AI relevance score. Both are kept and shown; the AI score never replaces the rule score.
 */
#[Fillable(['radar_project_id', 'kind', 'score', 'breakdown', 'explanation', 'model', 'content_hash', 'weights_hash', 'user_id', 'created_at'])]
class RadarEvaluation extends Model
{
    public const KIND_RULES = 'rules';

    public const KIND_AI = 'ai';

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'breakdown' => 'array',
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
