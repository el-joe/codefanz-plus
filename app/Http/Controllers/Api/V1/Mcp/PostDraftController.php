<?php

namespace App\Http\Controllers\Api\V1\Mcp;

use App\Actions\Blog\SavePost;
use App\Enums\ActorType;
use App\Enums\PostOrigin;
use App\Http\Requests\Api\Mcp\CreateBlogDraftRequest;
use App\Models\Post;
use App\Models\PostTranslation;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

/**
 * create_blog_draft: a new article in Draft with origin "mcp", authored by the client's owner. It goes through the
 * normal editorial workflow (review by someone other than the author, then publish) — nothing is published here.
 */
class PostDraftController extends McpController
{
    public function __invoke(CreateBlogDraftRequest $request, SavePost $savePost, AuditLogger $audit): JsonResponse
    {
        $client = $this->client($request);
        $translations = [];

        foreach ($request->validated('translations') as $locale => $fields) {
            $translations[$locale] = [
                'title' => trim(strip_tags($fields['title'])),
                'slug' => $this->uniqueSlug($locale, $fields['title']),
                'excerpt' => isset($fields['excerpt']) ? trim(strip_tags($fields['excerpt'])) : null,
                'body' => trim(Str::markdown($fields['body'], ['html_input' => 'strip', 'allow_unsafe_links' => false])),
                'seo_title' => isset($fields['seo_title']) ? trim(strip_tags($fields['seo_title'])) : null,
                'seo_description' => isset($fields['seo_description']) ? trim(strip_tags($fields['seo_description'])) : null,
                'focus_keyword' => isset($fields['focus_keyword']) ? trim(strip_tags($fields['focus_keyword'])) : null,
            ];
        }

        $post = $savePost->handle(null, [
            'origin' => PostOrigin::Mcp,
            'post_category_id' => $request->validated('post_category_id'),
            'is_single_language' => count($translations) === 1,
            'translations' => $translations,
        ], $client->owner)['post'];

        $audit->record(
            operation: 'mcp.blog_draft_created',
            auditable: $post,
            user: $client->owner,
            actorType: ActorType::McpClient,
            actorLabel: $client->actorLabel(),
            after: ['locales' => array_keys($translations), 'status' => $post->status->value, 'origin' => $post->origin->value],
        );

        return new JsonResponse(['data' => [
            'type' => 'post',
            'id' => $post->getKey(),
            'status' => $post->status->value,
            'origin' => $post->origin->value,
            'version' => $post->approvableVersion(),
            'translations' => collect($translations)->map(fn (array $fields): array => ['title' => $fields['title'], 'slug' => $fields['slug']])->all(),
            'next_step' => 'An editor must review this draft and someone other than its author must approve it before it can be published.',
        ]], 201);
    }

    private function uniqueSlug(string $locale, string $title): string
    {
        $base = Str::slug($title, '-', null) ?: 'article';
        $base = in_array($base, Post::RESERVED_SLUGS, true) ? $base.'-1' : mb_substr($base, 0, 180);
        $slug = $base;

        for ($suffix = 2; PostTranslation::query()->where('locale', $locale)->where('slug', $slug)->exists(); $suffix++) {
            $slug = $base.'-'.$suffix;
        }

        return $slug;
    }
}
