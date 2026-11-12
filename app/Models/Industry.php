<?php

namespace App\Models;

use App\Enums\ContentStatus;
use App\Models\Concerns\HasTranslations;
use Database\Factories\IndustryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @method IndustryTranslation|null translation(?string $locale = null)
 * @method IndustryTranslation|null exactTranslation(string $locale)
 */
#[Fillable(['icon', 'status', 'sort_order', 'published_at', 'created_by', 'updated_by'])]
class Industry extends Model
{
    /** @use HasFactory<IndustryFactory> */
    use HasFactory, HasTranslations, SoftDeletes;

    public const TRANSLATED_FIELDS = ['name', 'slug', 'summary', 'typical_solutions', 'seo_title', 'seo_description'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'sort_order' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ContentStatus::class,
            'sort_order' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<Industry>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', ContentStatus::Published);
    }

    /**
     * @param  Builder<Industry>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }
}
