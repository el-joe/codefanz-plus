<?php

namespace App\Actions\Blog;

use App\Enums\PostOrigin;
use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Blog\EditorialChecklist;
use App\Services\Blog\PostRevisions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Direct edits by human editors. Each save bumps the version (any pending AI/MCP proposal becomes stale), records
 * a revision when the content changed and re-evaluates the editorial checklist. Changing the content of an approved
 * or scheduled article withdraws the approval: it returns to Draft and must be reviewed again.
 */
class SavePost
{
    public function __construct(
        private AuditLogger $audit,
        private PostRevisions $revisions,
        private EditorialChecklist $checklist,
    ) {}

    /**
     * @param  array{post_category_id?: int|null, tag_ids?: list<int>|null, is_featured?: bool, is_single_language?: bool, cover_media_id?: int|null, lock_version?: int|null, translations: array<string, array<string, string|null>>, checklist?: array<string, bool>|null}  $data
     * @return array{post: Post, approval_withdrawn: bool}
     */
    public function handle(?Post $post, array $data, User $editor, string $reason = 'saved', ?int $restoredFromId = null): array
    {
        return DB::transaction(function () use ($post, $data, $editor, $reason, $restoredFromId): array {
            $attributes = [
                'post_category_id' => $data['post_category_id'] ?? null,
                'is_featured' => (bool) ($data['is_featured'] ?? false),
                'is_single_language' => (bool) ($data['is_single_language'] ?? false),
                'updated_by' => $editor->getKey(),
            ];

            if ($post === null) {
                $post = Post::create([
                    ...$attributes,
                    'author_id' => $editor->getKey(),
                    'status' => PostStatus::Draft,
                    'origin' => PostOrigin::Human,
                    'created_by' => $editor->getKey(),
                ]);
                $before = null;
            } else {
                $post = Post::query()->lockForUpdate()->findOrFail($post->getKey());

                if ((int) ($data['lock_version'] ?? 0) !== $post->approvableVersion()) {
                    throw ValidationException::withMessages([
                        'lock_version' => __('Someone else saved this article while you were editing. Reload to see their changes.'),
                    ]);
                }

                $before = $this->auditSnapshot($post);
                $post->fill($attributes);
                $post->lock_version++;
                $post->save();
            }

            $post->syncTranslations($data['translations'], Post::TRANSLATED_FIELDS, 'title');
            $post->tags()->sync($data['tag_ids'] ?? []);
            $post->syncMediaCollection('cover', array_filter([(int) ($data['cover_media_id'] ?? 0)]));

            $revision = $this->revisions->record($post, $editor, $before === null ? 'created' : $reason, restoredFromId: $restoredFromId);
            $approvalWithdrawn = false;

            if ($before !== null && $revision !== null && $post->status->isApprovalLocked()) {
                $post->forceFill(['status' => PostStatus::Draft, 'scheduled_for' => null, 'approved_at' => null])->save();
                $approvalWithdrawn = true;
            }

            $post->load(['translations', 'checklistItems']);
            $manual = array_key_exists('checklist', $data) ? array_map('boolval', (array) $data['checklist']) : $this->checklist->storedManual($post);
            $this->checklist->store($post, $this->checklist->evaluate($post, $manual), $editor);

            $this->audit->record(
                operation: $before === null ? 'post.created' : ($reason === 'restored' ? 'post.revision_restored' : 'post.updated'),
                auditable: $post,
                user: $editor,
                before: $before,
                after: [...$this->auditSnapshot($post), 'approval_withdrawn' => $approvalWithdrawn],
            );

            return ['post' => $post, 'approval_withdrawn' => $approvalWithdrawn];
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function auditSnapshot(Post $post): array
    {
        return [
            'status' => $post->status->value,
            'version' => $post->lock_version,
            'post_category_id' => $post->post_category_id,
            'is_featured' => $post->is_featured,
            'translations' => $post->translationSnapshot(['title', 'slug', 'seo_title', 'seo_description', 'focus_keyword']),
        ];
    }
}
