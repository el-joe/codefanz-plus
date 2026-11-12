<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Database\Factories\TagFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Blog tag with a name and slug per language.
 *
 * @method TagTranslation|null translation(?string $locale = null)
 * @method TagTranslation|null exactTranslation(string $locale)
 */
class Tag extends Model
{
    /** @use HasFactory<TagFactory> */
    use HasFactory, HasTranslations;

    public const TRANSLATED_FIELDS = ['name', 'slug'];

    /**
     * @return BelongsToMany<Post, $this>
     */
    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class)->withTimestamps();
    }
}
