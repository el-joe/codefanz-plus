<?php

namespace App\Services\Blog;

use App\Models\Post;
use App\Models\PostRevision;
use App\Models\User;

/**
 * Article revision history: one snapshot per content-changing save, restore and applied change request.
 */
class PostRevisions
{
    /**
     * Snapshot of everything a restore brings back.
     *
     * @return array{translations: array<string, array<string, string|null>>, meta: array{post_category_id: int|null, tag_ids: list<int>, is_featured: bool, is_single_language: bool, cover_media_id: int|null}}
     */
    public function snapshot(Post $post): array
    {
        $translations = $post->translations()->orderBy('locale')->get()
            ->mapWithKeys(fn ($translation): array => [$translation->locale => $translation->only(Post::TRANSLATED_FIELDS)])
            ->all();

        return [
            'translations' => $translations,
            'meta' => [
                'post_category_id' => $post->post_category_id,
                'tag_ids' => $post->tags()->orderBy('tags.id')->pluck('tags.id')->map(fn ($id): int => (int) $id)->all(),
                'is_featured' => (bool) $post->is_featured,
                'is_single_language' => (bool) $post->is_single_language,
                'cover_media_id' => ($cover = $post->cover()->value('media.id')) !== null ? (int) $cover : null,
            ],
        ];
    }

    /**
     * Store a revision unless nothing changed since the latest one. Returns the new revision or null.
     */
    public function record(Post $post, ?User $user, string $reason = 'saved', ?string $actorLabel = null, ?int $restoredFromId = null): ?PostRevision
    {
        $snapshot = $this->snapshot($post);
        $latest = $post->revisions()->first();

        if ($latest !== null && $reason !== 'restored' && $latest->snapshot == $snapshot) {
            return null;
        }

        return $post->revisions()->create([
            'user_id' => $user?->getKey(),
            'actor_label' => $actorLabel ?? $user?->name,
            'reason' => $reason,
            'restored_from_id' => $restoredFromId,
            'lock_version' => $post->lock_version,
            'snapshot' => $snapshot,
        ]);
    }

    /**
     * Readable text lines of every field of one locale, used by the side-by-side diff.
     *
     * @param  array<string, mixed>  $snapshot
     * @return list<string>
     */
    public function lines(array $snapshot, string $locale): array
    {
        $fields = $snapshot['translations'][$locale] ?? [];
        $lines = [];

        foreach (['title' => __('Title'), 'slug' => __('URL slug'), 'excerpt' => __('Excerpt'), 'seo_title' => __('SEO title'), 'seo_description' => __('Meta description'), 'focus_keyword' => __('Focus keyword'), 'canonical_url' => __('Canonical URL')] as $field => $label) {
            if (filled($fields[$field] ?? null)) {
                $lines[] = $label.': '.$fields[$field];
            }
        }

        foreach (self::htmlToLines((string) ($fields['body'] ?? '')) as $line) {
            $lines[] = $line;
        }

        return $lines;
    }

    /**
     * One line per block (paragraph, heading, list item, cell…), tags removed.
     *
     * @return list<string>
     */
    public static function htmlToLines(string $html): array
    {
        $text = preg_replace('/<(br|hr)\b[^>]*>/i', "\n", $html) ?? '';
        $text = preg_replace('/<\/(p|h[1-6]|li|blockquote|pre|tr|th|td|aside|figcaption|figure)>/i', "\n", $text) ?? '';
        $text = preg_replace('/<img\b[^>]*alt="([^"]*)"[^>]*>/i', "\n[image: $1]\n", $text) ?? '';
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return array_values(array_filter(array_map(fn (string $line): string => trim(preg_replace('/[ \t]+/u', ' ', $line) ?? ''), explode("\n", $text)), fn (string $line): bool => $line !== ''));
    }
}
