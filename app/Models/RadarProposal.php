<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A proposal draft for a radar project. It is text for a person to copy, edit and send themselves: nothing in the
 * application submits proposals to any platform.
 */
#[Fillable(['radar_project_id', 'locale', 'body', 'guardrail_flags', 'ai_generation_id', 'created_by', 'updated_by'])]
class RadarProposal extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'guardrail_flags' => 'array',
        ];
    }

    /**
     * @return BelongsTo<RadarProject, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(RadarProject::class, 'radar_project_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
