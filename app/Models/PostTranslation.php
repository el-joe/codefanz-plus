<?php

namespace App\Models;

use App\Services\Blog\ArticleBody;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One language of an article. Every write to `body` passes through the sanitizer, which also derives the
 * table of contents, word count and reading time — there is no other write path for article HTML.
 */
#[Fillable(['post_id', 'locale', 'title', 'slug', 'excerpt', 'body', 'seo_title', 'seo_description', 'focus_keyword', 'canonical_url'])]
class PostTranslation extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'toc' => 'array',
            'word_count' => 'integer',
            'reading_minutes' => 'integer',
        ];
    }

    /**
     * @return Attribute<string|null, string|null>
     */
    protected function body(): Attribute
    {
        return Attribute::make(
            set: function (?string $value): array {
                $prepared = app(ArticleBody::class)->prepare($value);

                return [
                    'body' => $prepared['body'] === '' ? null : $prepared['body'],
                    'toc' => json_encode($prepared['toc'], JSON_UNESCAPED_UNICODE),
                    'word_count' => $prepared['word_count'],
                    'reading_minutes' => $prepared['reading_minutes'],
                ];
            },
        );
    }

    /**
     * @return BelongsTo<Post, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * Plain-text summary for meta tags and feeds.
     */
    public function summary(): string
    {
        return $this->excerpt ?: str((string) strip_tags((string) $this->body))->squish()->limit(160)->toString();
    }
}
