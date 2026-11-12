<?php

namespace App\Actions\Blog;

use App\Enums\ActorType;
use App\Enums\Permission;
use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\User;
use App\Notifications\PostReviewed;
use App\Notifications\PostSubmittedForReview;
use App\Services\AuditLogger;
use App\Services\Blog\EditorialChecklist;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Editorial state transitions. Authorization (content.manage to submit, content.approve and — for AI drafts — not
 * being the author to review) is checked by PostPolicy before these run; the allowed from-states are enforced here.
 *
 * Only approved articles can be published or scheduled, and only through these methods or posts:publish-scheduled.
 */
class PostWorkflow
{
    public function __construct(private AuditLogger $audit, private EditorialChecklist $checklist) {}

    public function submit(Post $post, User $user): Post
    {
        $post = $this->transition($post, $user, [PostStatus::Draft, PostStatus::ChangesRequested], 'post.submitted', function (Post $post) use ($user): void {
            $this->ensureRequirements($post);
            $post->forceFill([
                'status' => PostStatus::InReview,
                'submitted_by' => $user->getKey(),
                'submitted_at' => now(),
                'review_note' => null,
            ])->save();
        });

        $reviewers = User::query()->holdingPermission(Permission::ContentApprove)->get()
            ->reject(fn (User $reviewer): bool => $reviewer->is($user) || ($post->isAiOrigin() && $reviewer->getKey() === $post->author_id))
            ->values();
        Notification::send($reviewers, new PostSubmittedForReview($post, $this->title($post), $user->name));

        return $post;
    }

    public function approve(Post $post, User $reviewer): Post
    {
        $post = $this->transition($post, $reviewer, [PostStatus::InReview], 'post.approved', function (Post $post) use ($reviewer): void {
            $this->ensureIndependentReviewer($post, $reviewer);
            $this->ensureRequirements($post);
            $post->forceFill([
                'status' => PostStatus::Approved,
                'reviewed_by' => $reviewer->getKey(),
                'reviewed_at' => now(),
                'approved_at' => now(),
                'review_note' => null,
            ])->save();
        });

        $this->notifyAuthor($post, $reviewer, approved: true);

        return $post;
    }

    public function requestChanges(Post $post, User $reviewer, string $note): Post
    {
        $post = $this->transition($post, $reviewer, [PostStatus::InReview, PostStatus::Approved, PostStatus::Scheduled], 'post.changes_requested', function (Post $post) use ($reviewer, $note): void {
            $post->forceFill([
                'status' => PostStatus::ChangesRequested,
                'reviewed_by' => $reviewer->getKey(),
                'reviewed_at' => now(),
                'approved_at' => null,
                'scheduled_for' => null,
                'review_note' => $note,
            ])->save();
        }, ['note' => $note]);

        $this->notifyAuthor($post, $reviewer, approved: false, note: $note);

        return $post;
    }

    public function publish(Post $post, User $reviewer): Post
    {
        return $this->transition($post, $reviewer, [PostStatus::Approved, PostStatus::Scheduled], 'post.published', function (Post $post) use ($reviewer): void {
            $this->ensureIndependentReviewer($post, $reviewer);
            $this->ensureRequirements($post);
            $post->forceFill(['status' => PostStatus::Published, 'published_at' => now(), 'scheduled_for' => null])->save();
        });
    }

    public function schedule(Post $post, User $reviewer, CarbonInterface $when): Post
    {
        if ($when->isPast()) {
            throw ValidationException::withMessages(['scheduled_for' => __('Choose a date and time in the future.')]);
        }

        return $this->transition($post, $reviewer, [PostStatus::Approved, PostStatus::Scheduled], 'post.scheduled', function (Post $post) use ($when, $reviewer): void {
            $this->ensureIndependentReviewer($post, $reviewer);
            $this->ensureRequirements($post);
            $post->forceFill(['status' => PostStatus::Scheduled, 'scheduled_for' => $when])->save();
        }, ['scheduled_for' => $when->toIso8601String()]);
    }

    public function unschedule(Post $post, User $reviewer): Post
    {
        return $this->transition($post, $reviewer, [PostStatus::Scheduled], 'post.unscheduled', function (Post $post): void {
            $post->forceFill(['status' => PostStatus::Approved, 'scheduled_for' => null])->save();
        });
    }

    public function archive(Post $post, User $reviewer): Post
    {
        return $this->transition($post, $reviewer, [PostStatus::Draft, PostStatus::ChangesRequested, PostStatus::Approved, PostStatus::Scheduled, PostStatus::Published], 'post.archived', function (Post $post): void {
            $post->forceFill(['status' => PostStatus::Archived, 'scheduled_for' => null])->save();
        });
    }

    /**
     * Archived articles return to Draft and need a fresh review before going live again.
     */
    public function reopen(Post $post, User $user): Post
    {
        return $this->transition($post, $user, [PostStatus::Archived], 'post.reopened', function (Post $post): void {
            $post->forceFill(['status' => PostStatus::Draft, 'approved_at' => null])->save();
        });
    }

    /**
     * Publish approved articles whose scheduled time has passed (posts:publish-scheduled). Returns the count.
     */
    public function publishDue(): int
    {
        $published = 0;

        Post::query()
            ->where('status', PostStatus::Scheduled)
            ->whereNotNull('scheduled_for')
            ->where('scheduled_for', '<=', now())
            ->whereNotNull('approved_at')
            ->whereNotNull('reviewed_by')
            ->orderBy('scheduled_for')
            ->pluck('id')
            ->each(function (int $id) use (&$published): void {
                DB::transaction(function () use ($id, &$published): void {
                    $post = Post::query()->lockForUpdate()->find($id);

                    if ($post === null || $post->status !== PostStatus::Scheduled || $post->approved_at === null || $post->scheduled_for === null || $post->scheduled_for->isFuture()) {
                        return;
                    }

                    $post->forceFill(['status' => PostStatus::Published, 'published_at' => $post->scheduled_for, 'scheduled_for' => null])->save();
                    $this->audit->record(operation: 'post.published', auditable: $post, actorType: ActorType::System, actorLabel: 'Scheduler', before: ['status' => PostStatus::Scheduled->value], after: ['status' => PostStatus::Published->value]);
                    $published++;
                });
            });

        return $published;
    }

    /**
     * @param  list<PostStatus>  $from
     * @param  callable(Post): void  $apply
     * @param  array<string, mixed>  $extra
     */
    private function transition(Post $post, User $user, array $from, string $operation, callable $apply, array $extra = []): Post
    {
        return DB::transaction(function () use ($post, $user, $from, $operation, $apply, $extra): Post {
            $post = Post::query()->lockForUpdate()->findOrFail($post->getKey());
            $before = $post->status;

            if (! in_array($before, $from, true)) {
                throw ValidationException::withMessages([
                    'status' => __('This action is not available while the article is “:status”.', ['status' => $before->label()]),
                ]);
            }

            $apply($post);

            $this->audit->record(
                operation: $operation,
                auditable: $post,
                user: $user,
                before: ['status' => $before->value],
                after: ['status' => $post->status->value, ...$extra],
            );

            return $post->load('translations');
        });
    }

    /**
     * AI drafts need a second person, even for super admins (Gate::before does not apply here).
     */
    private function ensureIndependentReviewer(Post $post, User $reviewer): void
    {
        if ($post->isAiOrigin() && $post->author_id !== null && $post->author_id === $reviewer->getKey()) {
            throw new AuthorizationException(__('An AI-generated draft must be reviewed by someone other than its author.'));
        }
    }

    private function ensureRequirements(Post $post): void
    {
        $post->loadMissing('translations');

        if (! EditorialChecklist::requirementsMet($this->checklist->evaluate($post))) {
            throw ValidationException::withMessages([
                'status' => __('Add the title, URL slug and excerpt in the default language first.'),
            ]);
        }
    }

    private function notifyAuthor(Post $post, User $reviewer, bool $approved, ?string $note = null): void
    {
        $author = $post->author_id !== null ? User::query()->whereKey($post->author_id)->where('is_active', true)->first() : null;

        if ($author !== null && ! $author->is($reviewer)) {
            $author->notify(new PostReviewed($post, $this->title($post), $approved, $reviewer->name, $note));
        }
    }

    private function title(Post $post): string
    {
        return $post->translation()?->title ?? __('Untitled');
    }
}
