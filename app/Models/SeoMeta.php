<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Per-locale SEO settings without a column on the entity's own translation: focus keyword, canonical override and
 * noindex for content (seoable), or title/description overrides for a fixed site page (route_key).
 */
#[Fillable(['seoable_type', 'seoable_id', 'route_key', 'locale', 'title', 'description', 'focus_keyword', 'canonical_url', 'noindex', 'updated_by'])]
class SeoMeta extends Model
{
    protected $table = 'seo_meta';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'noindex' => false,
        'lock_version' => 1,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'noindex' => 'boolean',
            'lock_version' => 'integer',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function seoable(): MorphTo
    {
        return $this->morphTo();
    }
}
