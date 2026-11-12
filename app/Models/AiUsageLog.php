<?php

namespace App\Models;

use App\Enums\AiFeature;
use App\Enums\AiUsageStatus;
use Database\Factories\AiUsageLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One AI provider call: tokens, estimated cost (US cents, four decimals), latency and outcome. Never holds prompts.
 */
#[Fillable([
    'feature', 'provider', 'model', 'input_tokens', 'output_tokens', 'estimated_cost_minor', 'currency',
    'latency_ms', 'status', 'error', 'user_id', 'lead_id', 'chat_session_id', 'created_at',
])]
class AiUsageLog extends Model
{
    /** @use HasFactory<AiUsageLogFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'feature' => AiFeature::class,
            'status' => AiUsageStatus::class,
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'estimated_cost_minor' => 'float',
            'latency_ms' => 'integer',
        ];
    }

    /**
     * @param  Builder<AiUsageLog>  $query
     */
    public function scopeInMonth(Builder $query, Carbon $month): void
    {
        $query->whereBetween('created_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()]);
    }
}
