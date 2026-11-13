<?php

namespace App\Http\Controllers\Api\V1\Mcp;

use App\Actions\Social\SaveSocialPost;
use App\Enums\ActorType;
use App\Enums\PostOrigin;
use App\Enums\SocialAccountStatus;
use App\Http\Requests\Api\Mcp\CreateSocialDraftRequest;
use App\Models\SocialAccount;
use App\Models\SocialPost;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;

/**
 * list_social_accounts (no tokens, ever) and create_social_draft: a Draft social post with origin "mcp" that must
 * pass the social workflow (review by someone other than its generator, approval, scheduling) before it posts.
 */
class SocialController extends McpController
{
    public function accounts(): JsonResponse
    {
        $accounts = SocialAccount::query()
            ->where('status', '!=', SocialAccountStatus::Disconnected->value)
            ->orderBy('platform')
            ->orderBy('name')
            ->get()
            ->map(fn (SocialAccount $account): array => [
                'id' => $account->id,
                'platform' => $account->platform->value,
                'name' => $account->name,
                'handle' => $account->handle,
                'status' => $account->status->value,
            ]);

        return new JsonResponse(['data' => $accounts]);
    }

    public function store(CreateSocialDraftRequest $request, SaveSocialPost $save, AuditLogger $audit): JsonResponse
    {
        $client = $this->client($request);
        $validated = $request->validated();
        $variants = [];

        foreach ($validated['variants'] as $variant) {
            $variants[(int) $variant['account_id']] = [
                'enabled' => true,
                'text' => strip_tags($variant['text']),
                'hashtags' => $variant['hashtags'] ?? null,
                'first_comment' => isset($variant['first_comment']) ? strip_tags($variant['first_comment']) : null,
                'include_media' => false,
            ];
        }

        $post = $save->handle(new SocialPost(['origin' => PostOrigin::Mcp, 'generated_by' => $client->owner_id]), [
            'title' => strip_tags($validated['title']),
            'locale' => $validated['locale'],
            'campaign' => $validated['campaign'] ?? null,
            'link_url' => $validated['link_url'] ?? null,
            'variants' => $variants,
        ], $client->owner);

        $audit->record(
            operation: 'mcp.social_draft_created',
            auditable: $post,
            user: $client->owner,
            actorType: ActorType::McpClient,
            actorLabel: $client->actorLabel(),
            after: ['title' => $post->title, 'accounts' => array_keys($variants)],
        );

        return new JsonResponse(['data' => [
            'id' => $post->ulid,
            'title' => $post->title,
            'status' => $post->status->value,
            'origin' => $post->origin->value,
            'variants' => $post->variants->map(fn ($variant): array => ['account_id' => $variant->social_account_id, 'platform' => $variant->platform->value])->all(),
            'next_step' => 'A social media manager must submit, approve (someone other than the requester) and schedule this post before anything is published.',
        ]], 201);
    }
}
