<?php

namespace App\Models;

use App\Enums\RadarBudgetType;
use App\Enums\RadarProjectStatus;
use App\Services\Money;
use Database\Factories\RadarProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A project collected from a radar platform. UNIQUE(platform, external_id) makes re-collection an update; the
 * content hash decides whether it is re-scored and may alert again.
 */
#[Fillable([
    'radar_platform_id', 'external_id', 'url', 'title', 'description', 'budget_min_minor', 'budget_max_minor', 'currency',
    'budget_type', 'skills', 'client_country', 'client_rating', 'client_spent_minor', 'client_payment_verified',
    'proposals_count', 'language', 'posted_at', 'collected_at', 'content_hash', 'source', 'status', 'score', 'ai_score',
    'lead_id', 'status_changed_by',
])]
class RadarProject extends Model
{
    /** @use HasFactory<RadarProjectFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'new',
        'budget_type' => 'unknown',
        'language' => 'en',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'budget_type' => RadarBudgetType::class,
            'status' => RadarProjectStatus::class,
            'skills' => 'array',
            'source' => 'array',
            'client_rating' => 'float',
            'client_payment_verified' => 'boolean',
            'budget_min_minor' => 'integer',
            'budget_max_minor' => 'integer',
            'client_spent_minor' => 'integer',
            'proposals_count' => 'integer',
            'score' => 'integer',
            'ai_score' => 'integer',
            'posted_at' => 'datetime',
            'collected_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<RadarPlatform, $this>
     */
    public function platform(): BelongsTo
    {
        return $this->belongsTo(RadarPlatform::class, 'radar_platform_id');
    }

    /**
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * @return HasMany<RadarEvaluation, $this>
     */
    public function evaluations(): HasMany
    {
        return $this->hasMany(RadarEvaluation::class);
    }

    /**
     * @return HasOne<RadarEvaluation, $this>
     */
    public function ruleEvaluation(): HasOne
    {
        return $this->hasOne(RadarEvaluation::class)->ofMany(['id' => 'max'], fn ($query) => $query->where('kind', RadarEvaluation::KIND_RULES));
    }

    /**
     * @return HasOne<RadarEvaluation, $this>
     */
    public function aiEvaluation(): HasOne
    {
        return $this->hasOne(RadarEvaluation::class)->ofMany(['id' => 'max'], fn ($query) => $query->where('kind', RadarEvaluation::KIND_AI));
    }

    /**
     * @return HasMany<RadarAlert, $this>
     */
    public function alerts(): HasMany
    {
        return $this->hasMany(RadarAlert::class);
    }

    /**
     * @return HasMany<RadarProposal, $this>
     */
    public function proposals(): HasMany
    {
        return $this->hasMany(RadarProposal::class);
    }

    /**
     * Human budget, e.g. "USD 3,000–5,000" or "USD 25–40 / hr"; null when the platform did not state one.
     */
    public function budgetLabel(): ?string
    {
        if ($this->currency === null || ($this->budget_min_minor === null && $this->budget_max_minor === null)) {
            return null;
        }

        $min = $this->budget_min_minor !== null ? Money::format($this->budget_min_minor, $this->currency) : null;
        $max = $this->budget_max_minor !== null ? Money::format($this->budget_max_minor, $this->currency) : null;
        $range = $min !== null && $max !== null && $min !== $max ? $min.' – '.$max : ($max ?? $min);

        return $this->budget_type === RadarBudgetType::Hourly ? __(':amount / hr', ['amount' => $range]) : $range;
    }

    /**
     * @return list<string>
     */
    public function skillList(): array
    {
        return array_values(array_filter(array_map('strval', (array) $this->skills), 'filled'));
    }
}
