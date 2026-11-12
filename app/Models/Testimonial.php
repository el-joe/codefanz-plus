<?php

namespace App\Models;

use App\Enums\ContentStatus;
use App\Models\Concerns\HasMediaCollections;
use App\Models\Concerns\HasTranslations;
use Database\Factories\TestimonialFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A client quote. It is only ever shown publicly when published AND the client's consent is confirmed.
 *
 * @method TestimonialTranslation|null translation(?string $locale = null)
 * @method TestimonialTranslation|null exactTranslation(string $locale)
 */
#[Fillable([
    'portfolio_project_id', 'author_name', 'company', 'rating', 'consent_confirmed', 'consent_confirmed_at', 'consent_confirmed_by',
    'status', 'is_featured', 'sort_order', 'published_at', 'created_by', 'updated_by',
])]
class Testimonial extends Model
{
    /** @use HasFactory<TestimonialFactory> */
    use HasFactory, HasMediaCollections, HasTranslations, SoftDeletes;

    public const TRANSLATED_FIELDS = ['quote', 'author_role'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'consent_confirmed' => false,
        'is_featured' => false,
        'sort_order' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ContentStatus::class,
            'consent_confirmed' => 'boolean',
            'consent_confirmed_at' => 'datetime',
            'is_featured' => 'boolean',
            'rating' => 'integer',
            'sort_order' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    /**
     * Published testimonials whose client agreed to publication.
     *
     * @param  Builder<Testimonial>  $query
     */
    public function scopePubliclyVisible(Builder $query): void
    {
        $query->where('status', ContentStatus::Published)->where('consent_confirmed', true);
    }

    /**
     * @param  Builder<Testimonial>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderByDesc('id');
    }

    /**
     * @return BelongsTo<PortfolioProject, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(PortfolioProject::class, 'portfolio_project_id');
    }

    /**
     * @return MorphToMany<Media, $this>
     */
    public function avatar(): MorphToMany
    {
        return $this->mediaIn('avatar');
    }

    public function avatarImage(): ?Media
    {
        return $this->avatar->first();
    }

    /**
     * Up to two initials for the avatar placeholder.
     */
    public function initials(): string
    {
        return collect(preg_split('/\s+/u', trim($this->author_name)) ?: [])
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
    }
}
