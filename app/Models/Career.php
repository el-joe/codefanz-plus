<?php

namespace App\Models;

use App\Enums\ContentStatus;
use App\Enums\EmploymentType;
use App\Models\Concerns\HasTranslations;
use Database\Factories\CareerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An open position. Visitors only see published positions that have not passed their closing date.
 *
 * @method CareerTranslation|null translation(?string $locale = null)
 * @method CareerTranslation|null exactTranslation(string $locale)
 */
#[Fillable(['employment_type', 'is_remote', 'status', 'closes_at', 'sort_order', 'published_at', 'created_by', 'updated_by'])]
class Career extends Model
{
    /** @use HasFactory<CareerFactory> */
    use HasFactory, HasTranslations, SoftDeletes;

    public const TRANSLATED_FIELDS = ['title', 'slug', 'department', 'location', 'summary', 'responsibilities', 'requirements', 'body', 'seo_title', 'seo_description'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'employment_type' => 'full_time',
        'is_remote' => false,
        'status' => 'draft',
        'sort_order' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'employment_type' => EmploymentType::class,
            'status' => ContentStatus::class,
            'is_remote' => 'boolean',
            'closes_at' => 'date',
            'sort_order' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    /**
     * Published positions still accepting applications (the closing date is inclusive).
     *
     * @param  Builder<Career>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', ContentStatus::Published)
            ->where(fn (Builder $date) => $date->whereNull('closes_at')->orWhereDate('closes_at', '>=', today()));
    }

    /**
     * @param  Builder<Career>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderByDesc('published_at')->orderByDesc('id');
    }

    public function isOpen(): bool
    {
        return $this->status === ContentStatus::Published && ($this->closes_at === null || ! $this->closes_at->isBefore(today()));
    }
}
