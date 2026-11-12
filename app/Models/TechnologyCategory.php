<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Database\Factories\TechnologyCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @method TechnologyCategoryTranslation|null translation(?string $locale = null)
 * @method TechnologyCategoryTranslation|null exactTranslation(string $locale)
 */
#[Fillable(['key', 'sort_order'])]
class TechnologyCategory extends Model
{
    /** @use HasFactory<TechnologyCategoryFactory> */
    use HasFactory, HasTranslations;

    public const TRANSLATED_FIELDS = ['name'];

    /**
     * @param  Builder<TechnologyCategory>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<Technology, $this>
     */
    public function technologies(): HasMany
    {
        return $this->hasMany(Technology::class);
    }
}
