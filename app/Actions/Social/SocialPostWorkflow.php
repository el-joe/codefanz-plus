<?php

namespace App\Actions\Social;

use App\Enums\Permission;
use App\Enums\SocialPostStatus;
use App\Enums\SocialVariantStatus;
use App\Jobs\PublishSocialPost;
use App\Models\SocialPost;
use App\Models\SocialPostVariant;
use App\Models\User;
use App\Notifications\SocialPostReviewed;
use App\Notifications\SocialPostSubmittedForReview;
use App\Services\AuditLogger;
use App\Services\Social\SocialDelivery;
use App\Services\Social\SocialPostValidator;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * State transitions of a social post. Policies decide who may call each one (social.manage to write and submit,
 * social.approve to review and schedule); this class enforces the allowed from-states, the platform rules and the
 * four-eyes rule for AI posts (also for super admins, whom Gate::before lets through the policy).
 *
 * Nothing reaches the publish queue unless it was approved: schedule, publish-now and the calendar all require an
 * approved post, and the queue re-checks the approval before every platform call.
 */
class SocialPostWorkflow
{
    public function __construct(
        private AuditLogger $audit,
        private SocialPostValidator $validator,
        private SocialDelivery $delivery,
    ) {}

    public function submit(SocialPost $post, User $user): SocialPost
    {
        $post = $this->transition($post, $user, [SocialPostStatus::Draft, SocialPostStatus::ChangesRequested], 'social_post.submitted', function (SocialPost $post) use ($user): void {
            $this->ensureValid($post);
            $post->forceFill(['status' => SocialPostStatus::InReview, 'submitted_by' => $user->getKey(), 'submitted_at' => now(), 'review_note' => null])->save();
        });

        $reviewers = User::query()->holdingPermission(Permission::SocialApprove)->get()
            ->reject(fn (User $reviewer): bool => $reviewer->is($user) || $post->isGeneratedBy($reviewer))
            ->values();
        Notification::send($reviewers, new SocialPostSubmittedForReview($post, $user->name));

        return $post;
    }

    public function approve(SocialPost $post, User $reviewer): SocialPost
    {
        $post = $this->transition($post, $reviewer, [SocialPostStatus::InReview], 'social_post.approved', function (SocialPost $post) use ($reviewer): void {
            $this->ensureIndependentReviewer($post, $reviewer);
            $this->ensureValid($post);
            $post->forceFill(['status' => SocialPostStatus::Approved, 'reviewed_by' => $reviewer->getKey(), 'approved_at' => now(), 'review_note' => null])->save();
        });

        $this->notifyAuthor($post, $reviewer, approved: true);

        return $post;
    }

    public function requestChanges(SocialPost $post, User $reviewer, string $note): SocialPost
    {
        $post = $this->transition($post, $reviewer, [SocialPostStatus::InReview, SocialPostStatus::Approved, SocialPostStatus::Scheduled], 'social_post.changes_requested', function (SocialPost $post) use ($reviewer, $note): void {
            $post->forceFill(['status' => SocialPostStatus::ChangesRequested, 'reviewed_by' => $reviewer->getKey(), 'approved_at' => null, 'scheduled_at' => null, 'review_note' => $note])->save();
        }, ['note' => $note]);

        $this->notifyAuthor($post, $reviewer, approved: false, note: $note);

        return $post;
    }

    /**
     * Schedule (or reschedule, e.g. from the calendar) an approved post.
     */
    public function schedule(SocialPost $post, User $reviewer, CarbonInterface $when): SocialPost
    {
        if ($when->isPast()) {
            throw ValidationException::withMessages(['scheduled_for' => __('Choose a date and time in the future.')]);
        }

        return $this->transition($post, $reviewer, [SocialPostStatus::Approved, SocialPostStatus::Scheduled], 'social_post.scheduled', function (SocialPost $post) use ($reviewer, $when): void {
            $this->ensureApproved($post);
            $this->ensureIndependentReviewer($post, $reviewer);
            $post->forceFill(['status' => SocialPostStatus::Scheduled, 'scheduled_at' => $when])->save();
        }, ['scheduled_at' => $when->toIso8601String()], unavailable: 'Only approved posts can be scheduled. This post is “:status”.');
    }

    public function unschedule(SocialPost $post, User $reviewer): SocialPost
    {
        return $this->transition($post, $reviewer, [SocialPostStatus::Scheduled], 'social_post.unscheduled', function (SocialPost $post): void {
            $post->forceFill(['status' => SocialPostStatus::Approved, 'scheduled_at' => null])->save();
        });
    }

    /**
     * Queue an approved post right away (connected accounts publish, the others become ready for manual export).
     */
    public function publishNow(SocialPost $post, User $reviewer): SocialPost
    {
        if ($this->delivery->publishingPaused($post)) {
            throw ValidationException::withMessages(['status' => __('Social publishing is paused by a kill switch.')]);
        }

        $post = $this->transition($post, $reviewer, [SocialPostStatus::Approved, SocialPostStatus::Scheduled], 'social_post.publish_now', function (SocialPost $post) use ($reviewer): void {
            $this->ensureApproved($post);
            $this->ensureIndependentReviewer($post, $reviewer);
            $post->forceFill(['status' => SocialPostStatus::Scheduled, 'scheduled_at' => now()])->save();
        }, unavailable: 'Only approved posts can be published. This post is “:status”.');

        $this->delivery->start($post->id);

        return $post->refresh();
    }

    public function cancel(SocialPost $post, User $user): SocialPost
    {
        return $this->transition($post, $user, [SocialPostStatus::Draft, SocialPostStatus::InReview, SocialPostStatus::ChangesRequested, SocialPostStatus::Approved, SocialPostStatus::Scheduled, SocialPostStatus::Failed], 'social_post.cancelled', function (SocialPost $post): void {
            $post->variants()->whereNotIn('status', [SocialVariantStatus::Published->value, SocialVariantStatus::ExportedManually->value])->update(['status' => SocialVariantStatus::Cancelled->value]);
            $post->forceFill(['status' => SocialPostStatus::Cancelled, 'scheduled_at' => null])->save();
        });
    }

    /**
     * Send a failed variant back to the queue (an approver confirms the post is not live yet).
     */
    public function retry(SocialPostVariant $variant, User $reviewer): SocialPostVariant
    {
        return DB::transaction(function () use ($variant, $reviewer): SocialPostVariant {
            $variant = SocialPostVariant::query()->lockForUpdate()->findOrFail($variant->getKey());
            $post = SocialPost::query()->lockForUpdate()->findOrFail($variant->social_post_id);

            if ($variant->status !== SocialVariantStatus::Failed || $variant->external_id !== null) {
                throw ValidationException::withMessages(['status' => __('Only failed variants can be retried.')]);
            }

            $this->ensureApproved($post);
            $this->ensureIndependentReviewer($post, $reviewer);

            if ($this->delivery->publishingPaused($post)) {
                throw ValidationException::withMessages(['status' => __('Social publishing is paused by a kill switch.')]);
            }

            if (! $variant->account->canPublishDirectly()) {
                throw ValidationException::withMessages(['status' => __('The account cannot publish directly. Use the manual export instead.')]);
            }

            $variant->forceFill(['status' => SocialVariantStatus::Queued, 'last_error' => null])->save();
            $post->forceFill(['status' => SocialPostStatus::Publishing])->save();
            PublishSocialPost::dispatch($variant->id)->afterCommit();

            $this->audit->record(operation: 'social_post.retry', auditable: $post, user: $reviewer, after: ['variant' => $variant->id, 'platform' => $variant->platform->value]);

            return $variant;
        });
    }

    /**
     * Record that an approved variant was posted by hand, with its public URL.
     */
    public function markPostedManually(SocialPostVariant $variant, User $user, string $url): SocialPostVariant
    {
        return DB::transaction(function () use ($variant, $user, $url): SocialPostVariant {
            $variant = SocialPostVariant::query()->lockForUpdate()->findOrFail($variant->getKey());
            $post = SocialPost::query()->lockForUpdate()->findOrFail($variant->social_post_id);

            $this->ensureApproved($post);

            $waitingForExport = $variant->status->canBeMarkedPosted()
                || ($variant->status === SocialVariantStatus::Pending && in_array($post->status, [SocialPostStatus::Approved, SocialPostStatus::Scheduled], true) && ! $variant->account->canPublishDirectly());

            if (! $waitingForExport) {
                throw ValidationException::withMessages(['published_url' => __('This variant is not waiting for a manual export (it is “:status”).', ['status' => $variant->status->label()])]);
            }

            $host = strtolower((string) parse_url($url, PHP_URL_HOST));
            $matches = collect($variant->platform->hosts())->contains(fn (string $allowed): bool => $host === $allowed || str_ends_with($host, '.'.$allowed));

            if (! $matches) {
                throw ValidationException::withMessages(['published_url' => __('Paste the link of the post on :platform.', ['platform' => $variant->platform->label()])]);
            }

            $before = $variant->status->value;
            $variant->forceFill([
                'status' => SocialVariantStatus::ExportedManually,
                'published_url' => $url,
                'exported_by' => $user->getKey(),
                'exported_at' => now(),
                'last_error' => null,
            ])->save();

            if (in_array($post->status, [SocialPostStatus::Approved, SocialPostStatus::Scheduled], true)) {
                $post->forceFill(['status' => SocialPostStatus::Publishing])->save();
            }

            $this->audit->record(operation: 'social_post.exported_manually', auditable: $post, user: $user, before: ['variant_status' => $before], after: ['variant' => $variant->id, 'platform' => $variant->platform->value, 'url' => $url]);
            $this->delivery->syncPostStatus($post);

            return $variant;
        });
    }

    /**
     * @param  list<SocialPostStatus>  $from
     * @param  callable(SocialPost): void  $apply
     * @param  array<string, mixed>  $extra
     */
    private function transition(SocialPost $post, User $user, array $from, string $operation, callable $apply, array $extra = [], ?string $unavailable = null): SocialPost
    {
        return DB::transaction(function () use ($post, $user, $from, $operation, $apply, $extra, $unavailable): SocialPost {
            $post = SocialPost::query()->lockForUpdate()->findOrFail($post->getKey());
            $before = $post->status;

            if (! in_array($before, $from, true)) {
                throw ValidationException::withMessages([
                    'status' => __($unavailable ?? 'This action is not available while the post is “:status”.', ['status' => $before->label()]),
                ]);
            }

            $apply($post);

            $this->audit->record(operation: $operation, auditable: $post, user: $user, before: ['status' => $before->value], after: ['status' => $post->status->value, ...$extra]);

            return $post->load('variants');
        });
    }

    private function ensureApproved(SocialPost $post): void
    {
        if (! $post->isApproved()) {
            throw ValidationException::withMessages(['status' => __('This post has not been approved.')]);
        }
    }

    /**
     * AI posts need a second person, even for super admins.
     */
    private function ensureIndependentReviewer(SocialPost $post, User $reviewer): void
    {
        if ($post->isGeneratedBy($reviewer)) {
            throw new AuthorizationException(__('An AI-generated post must be approved and scheduled by someone other than the person who generated it.'));
        }
    }

    private function ensureValid(SocialPost $post): void
    {
        $errors = $this->validator->errorsFor($post);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function notifyAuthor(SocialPost $post, User $reviewer, bool $approved, ?string $note = null): void
    {
        $author = $post->author_id !== null ? User::query()->whereKey($post->author_id)->where('is_active', true)->first() : null;

        if ($author !== null && ! $author->is($reviewer)) {
            $author->notify(new SocialPostReviewed($post, $approved, $reviewer->name, $note));
        }
    }
}
