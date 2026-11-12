<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Database\Factories\PortfolioCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Portfolio tab (e.g. SaaS, Mobile, E-commerce).
 *
 * @method PortfolioCategoryTranslation|null translation(?string $locale = null)
 * @method PortfolioCategoryTranslation|null exactTranslation(string $locale)
 */
#[Fillable(['sort_order'])]
class PortfolioCategory extends Model
{
    /** @use HasFactory<PortfolioCategoryFactory> */
    use HasFactory, HasTranslations;

    public const TRANSLATED_FIELDS = ['name', 'slug'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'sort_order' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /**
     * @param  Builder<PortfolioCategory>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<PortfolioProject, $this>
     */
    public function projects(): HasMany
    {
        return $this->hasMany(PortfolioProject::class);
    }
}
