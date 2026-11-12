<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Database\Factories\ProductVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A released product version with a translatable Markdown changelog.
 *
 * @method ProductVersionTranslation|null translation(?string $locale = null)
 * @method ProductVersionTranslation|null exactTranslation(string $locale)
 */
#[Fillable(['product_id', 'version', 'released_at'])]
class ProductVersion extends Model
{
    /** @use HasFactory<ProductVersionFactory> */
    use HasFactory, HasTranslations;

    public const TRANSLATED_FIELDS = ['changelog'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'released_at' => 'date',
        ];
    }

    /**
     * Newest release first.
     *
     * @param  Builder<ProductVersion>  $query
     */
    public function scopeLatestFirst(Builder $query): void
    {
        $query->orderByDesc('released_at')->orderByDesc('id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
