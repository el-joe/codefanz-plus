<?php

namespace App\Actions\Social;

use App\Enums\SocialPostStatus;
use App\Enums\SocialVariantStatus;
use App\Models\SocialAccount;
use App\Models\SocialPost;
use App\Models\SocialPostVariant;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates or updates a social post and its variants (one per chosen account) from validated composer input.
 * Editing an approved or scheduled post sends it back to draft: the approval covered the old content only.
 */
class SaveSocialPost
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data  validated SaveSocialPostRequest data
     */
    public function handle(SocialPost $post, array $data, User $user): SocialPost
    {
        return DB::transaction(function () use ($post, $data, $user): SocialPost {
            $creating = ! $post->exists;
            $before = null;

            if (! $creating) {
                $post = SocialPost::query()->lockForUpdate()->findOrFail($post->getKey());

                if (! $post->status->isEditable()) {
                    throw ValidationException::withMessages(['status' => __('This post can no longer be edited while it is “:status”.', ['status' => $post->status->label()])]);
                }

                if (isset($data['lock_version']) && (int) $data['lock_version'] !== $post->lock_version) {
                    throw ValidationException::withMessages(['lock_version' => __('Someone else saved this post in the meantime. Reload to see their changes.')]);
                }

                $before = ['status' => $post->status->value, 'title' => $post->title];
            }

            $mediaIds = array_values(array_map('intval', $data['media_ids'] ?? []));
            $alts = [];

            foreach ($mediaIds as $mediaId) {
                $alt = trim((string) ($data['media_alt'][$mediaId] ?? ''));

                if ($alt !== '') {
                    $alts[(string) $mediaId] = $alt;
                }
            }

            $post->fill([
                'title' => $data['title'],
                'campaign' => filled($data['campaign'] ?? null) ? trim((string) $data['campaign']) : null,
                'locale' => $data['locale'],
                'link_url' => $data['link_url'] ?? null,
                'utm' => array_filter((array) ($data['utm'] ?? []), fn ($value): bool => filled($value)) ?: null,
                'media_alt' => $alts ?: null,
                'planned_at' => $data['planned_at'] ?? null,
            ]);

            $wasApproved = in_array($post->status, [SocialPostStatus::Approved, SocialPostStatus::Scheduled], true);

            if ($creating) {
                $post->author_id = $user->getKey();
            } else {
                $post->lock_version = $post->lock_version + 1;
            }

            if ($wasApproved) {
                $post->forceFill(['status' => SocialPostStatus::Draft, 'approved_at' => null, 'reviewed_by' => null, 'scheduled_at' => null]);
            }

            $post->save();
            $post->syncMediaCollection(SocialPost::MEDIA_COLLECTION, $mediaIds);

            $enabled = collect($data['variants'] ?? [])->filter(fn ($variant): bool => (bool) ($variant['enabled'] ?? false));
            $accounts = SocialAccount::query()->whereKey($enabled->keys()->map(fn ($id): int => (int) $id)->all())->get()->keyBy('id');

            $post->variants()->whereNotIn('social_account_id', $accounts->keys()->all())->delete();

            foreach ($accounts as $accountId => $account) {
                $input = $enabled[$accountId] ?? $enabled[(string) $accountId];
                $variant = $post->variants()->firstOrNew(['social_account_id' => $accountId]);
                $variant->fill([
                    'platform' => $account->platform,
                    'text' => trim((string) ($input['text'] ?? '')),
                    'hashtags' => filled($input['hashtags'] ?? null) ? implode(' ', SocialPostVariant::parseHashtags((string) $input['hashtags'])) : null,
                    'first_comment' => $account->platform->supportsFirstComment() && filled($input['first_comment'] ?? null) ? trim((string) $input['first_comment']) : null,
                    'include_media' => (bool) ($input['include_media'] ?? false),
                ]);
                $variant->status = SocialVariantStatus::Pending;
                $variant->save();
            }

            $this->audit->record(
                operation: $creating ? 'social_post.created' : 'social_post.updated',
                auditable: $post,
                user: $user,
                before: $before,
                after: ['status' => $post->status->value, 'title' => $post->title, 'accounts' => $accounts->keys()->all(), 'approval_reset' => $wasApproved],
            );

            return $post->load('variants');
        });
    }
}
