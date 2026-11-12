<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_id', 'locale', 'name', 'slug', 'tagline', 'description', 'features', 'licensing_notes', 'seo_title', 'seo_description'])]
class ProductTranslation extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'features' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Features filled in this language, in order.
     *
     * @return list<array{title: string, description: string|null}>
     */
    public function filledFeatures(): array
    {
        return array_values(array_filter($this->features ?? [], fn (mixed $feature): bool => is_array($feature) && filled($feature['title'] ?? null)));
    }
}
