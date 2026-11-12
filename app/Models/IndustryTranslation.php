<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['industry_id', 'locale', 'name', 'slug', 'summary', 'typical_solutions', 'seo_title', 'seo_description'])]
class IndustryTranslation extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'typical_solutions' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Industry, $this>
     */
    public function industry(): BelongsTo
    {
        return $this->belongsTo(Industry::class);
    }
}
