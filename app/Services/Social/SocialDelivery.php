<?php

namespace App\Services\Social;

use App\Enums\ActorType;
use App\Enums\KillSwitchKey;
use App\Enums\SocialAccountStatus;
use App\Enums\SocialPostStatus;
use App\Enums\SocialVariantStatus;
use App\Exceptions\SocialApiException;
use App\Jobs\PublishSocialPost;
use App\Models\SocialAccount;
use App\Models\SocialPost;
use App\Models\SocialPostVariant;
use App\Services\AuditLogger;
use App\Services\KillSwitches;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * The publish queue. dispatchDue() (social:publish-due, every minute) moves approved posts whose time has come to
 * "publishing": variants on connected accounts are queued as PublishSocialPost jobs, the others become "ready for
 * manual export". deliver() is the job body and the only code that calls a platform to publish.
 *
 * Guarantees:
 *  - Nothing unapproved is sent: approval is re-checked inside the row lock right before the call.
 *  - Kill switches (social_publishing; ai_automation for AI-origin posts) are checked here, not only in the UI.
 *  - No double posts: a variant with an external id is never sent again; a variant found in "publishing" (the
 *    previous attempt's outcome is unknown) is not retried automatically; uploads and Instagram containers are
 *    remembered on the variant; only errors that certainly did not create a post are retried, with backoff.
 */
class SocialDelivery
{
    public function __construct(
        private KillSwitches $killSwitches,
        private SocialPublisherManager $publishers,
        private SocialTokenRefresher $tokens,
        private AuditLogger $audit,
    ) {}

    public function publishingPaused(?SocialPost $post = null): bool
    {
        return $this->killSwitches->isActive(KillSwitchKey::SocialPublishing)
            || ($post?->isAiOrigin() && $this->killSwitches->isActive(KillSwitchKey::AiAutomation));
    }

    /**
     * @return int number of posts moved to publishing
     */
    public function dispatchDue(): int
    {
        if ($this->publishingPaused()) {
            return 0;
        }

        $count = 0;

        SocialPost::query()
            ->where('status', SocialPostStatus::Scheduled)
            ->whereNotNull('approved_at')
            ->whereNotNull('reviewed_by')
            ->where('scheduled_at', '<=', now())
            ->orderBy('scheduled_at')
            ->pluck('id')
            ->each(function (int $id) use (&$count): void {
                if ($this->start($id)) {
                    $count++;
                }
            });

        return $count;
    }

    /**
     * Move one due, approved post to publishing (also used by "Publish now").
     */
    public function start(int $postId): bool
    {
        return DB::transaction(function () use ($postId): bool {
            $post = SocialPost::query()->lockForUpdate()->find($postId);

            if ($post === null || $post->status !== SocialPostStatus::Scheduled || ! $post->isApproved() || $post->scheduled_at === null || $post->scheduled_at->isFuture() || $this->publishingPaused($post)) {
                return false;
            }

            $post->load('variants.account');
            $queued = [];
            $manual = [];

            foreach ($post->variants as $variant) {
                if ($variant->status !== SocialVariantStatus::Pending) {
                    continue;
                }

                if ($variant->account->canPublishDirectly() && ! $variant->account->trashed()) {
                    $variant->forceFill(['status' => SocialVariantStatus::Queued, 'last_error' => null])->save();
                    PublishSocialPost::dispatch($variant->id)->afterCommit();
                    $queued[] = $variant->platform->value;
                } else {
                    $variant->forceFill(['status' => SocialVariantStatus::ReadyForExport, 'last_error' => $variant->account->status->label()])->save();
                    $manual[] = $variant->platform->value;
                }
            }

            $post->forceFill(['status' => SocialPostStatus::Publishing])->save();
            $this->audit->record(operation: 'social_post.queued', auditable: $post, actorType: ActorType::System, actorLabel: 'Scheduler', before: ['status' => SocialPostStatus::Scheduled->value], after: ['status' => SocialPostStatus::Publishing->value, 'queued' => $queued, 'manual_export' => $manual]);
            $this->syncPostStatus($post);

            return true;
        });
    }

    /**
     * Publish one variant. Returns the number of seconds after which the job should run again, or null when done.
     */
    public function deliver(int $variantId, int $attempt = 1, ?int $maxAttempts = null): ?int
    {
        $maxAttempts ??= (int) config('codefanz.social.publish_tries', 5);

        $prepared = DB::transaction(function () use ($variantId): array|int|null {
            $variant = SocialPostVariant::query()->lockForUpdate()->find($variantId);

            if ($variant === null || $variant->external_id !== null || $variant->status->isDone()) {
                return null;
            }

            $post = SocialPost::query()->lockForUpdate()->find($variant->social_post_id);

            if ($variant->status === SocialVariantStatus::Publishing) {
                $this->fail($variant, __('The previous attempt ended without an answer from the platform. Check the account before retrying, so the post is not published twice.'), 'unknown');

                return null;
            }

            if ($variant->status !== SocialVariantStatus::Queued || $post === null || $post->status !== SocialPostStatus::Publishing || ! $post->isApproved()) {
                return null;
            }

            if ($this->publishingPaused($post)) {
                $variant->forceFill(['status' => SocialVariantStatus::Pending, 'last_error' => __('Paused by a kill switch.')])->save();

                if ($post->variants()->whereIn('status', [SocialVariantStatus::Queued->value, SocialVariantStatus::Publishing->value])->doesntExist()) {
                    $post->forceFill(['status' => SocialPostStatus::Scheduled])->save();
                }

                $this->audit->record(operation: 'social_post.publish_blocked', auditable: $post, actorType: ActorType::System, actorLabel: 'Publish queue', after: ['variant' => $variant->id, 'reason' => 'kill_switch'], result: 'failure');

                return null;
            }

            $account = SocialAccount::withTrashed()->find($variant->social_account_id);

            if ($account === null || ! $account->canPublishDirectly() || $account->trashed()) {
                $this->toManualExport($variant, $account?->status->label() ?? __('Account removed'));

                return null;
            }

            $limiterKey = 'social-publish:'.$variant->platform->value;
            $perMinute = (int) config('codefanz.social.rate_limits.'.$variant->platform->value, 10);

            if (RateLimiter::tooManyAttempts($limiterKey, $perMinute)) {
                return max(1, RateLimiter::availableIn($limiterKey));
            }

            RateLimiter::hit($limiterKey, 60);
            $variant->forceFill(['status' => SocialVariantStatus::Publishing, 'attempts' => $variant->attempts + 1])->save();

            return [$variant->id, $account->id];
        });

        if (! is_array($prepared)) {
            return $prepared;
        }

        $variant = SocialPostVariant::query()->with('post')->findOrFail($prepared[0]);
        $account = SocialAccount::query()->findOrFail($prepared[1]);

        try {
            if ($this->tokens->needsRefresh($account) || $account->tokenExpired()) {
                $account = $this->tokens->refresh($account);

                if (! $account->canPublishDirectly()) {
                    $this->toManualExport($variant, $account->status->label());

                    return null;
                }
            }

            $publisher = $this->publishers->for($variant->platform) ?? throw new SocialApiException(__('No publishing driver for this platform.'));
            $result = $publisher->publish($variant, $account);
        } catch (SocialApiException $exception) {
            return $this->handleFailure($variant, $account, $exception, $attempt, $maxAttempts);
        } catch (Throwable $exception) {
            Log::error('Social publish failed unexpectedly.', ['variant' => $variant->id, 'error' => $exception->getMessage()]);
            $this->fail($variant, __('Unexpected error while publishing. Check the account before retrying.'), 'unknown');

            return null;
        }

        DB::transaction(function () use ($variant, $result): void {
            $variant->forceFill([
                'status' => SocialVariantStatus::Published,
                'external_id' => $result->externalId,
                'published_url' => $result->url,
                'platform_response' => $result->response,
                'published_at' => now(),
                'last_error' => null,
            ])->save();

            $this->audit->record(operation: 'social_post.published', auditable: $variant->post, actorType: ActorType::System, actorLabel: 'Publish queue', after: ['variant' => $variant->id, 'platform' => $variant->platform->value, 'external_id' => $result->externalId, 'url' => $result->url, 'attempts' => $variant->attempts]);
            $this->syncPostStatus($variant->post);
        });

        return null;
    }

    private function handleFailure(SocialPostVariant $variant, SocialAccount $account, SocialApiException $exception, int $attempt, int $maxAttempts): ?int
    {
        switch ($exception->kind) {
            case SocialApiException::TRANSIENT:
                if ($attempt < $maxAttempts) {
                    $variant->forceFill(['status' => SocialVariantStatus::Queued, 'last_error' => $exception->getMessage()])->save();
                    $backoff = (array) config('codefanz.social.publish_backoff', [60]);

                    return (int) ($backoff[min($attempt - 1, count($backoff) - 1)] ?? 60);
                }

                $this->fail($variant, __('Gave up after :count attempts: :error', ['count' => $attempt, 'error' => $exception->getMessage()]), 'transient');

                return null;

            case SocialApiException::AUTH:
                $account->forgetTokens(SocialAccountStatus::TokenExpired);
                $account->forceFill(['last_error' => $exception->getMessage()])->save();
                $this->toManualExport($variant, $exception->getMessage());

                return null;

            case SocialApiException::APP_APPROVAL:
                $account->forceFill(['status' => SocialAccountStatus::RequiresAppApproval, 'last_error' => $exception->getMessage()])->save();
                $this->toManualExport($variant, $exception->getMessage());

                return null;

            default:
                $this->fail($variant, $exception->getMessage(), $exception->kind);

                return null;
        }
    }

    private function fail(SocialPostVariant $variant, string $reason, string $kind): void
    {
        $variant->forceFill(['status' => SocialVariantStatus::Failed, 'last_error' => mb_substr($reason, 0, 1000)])->save();
        $post = $variant->post()->first();
        $this->audit->record(operation: 'social_post.publish_failed', auditable: $post, actorType: ActorType::System, actorLabel: 'Publish queue', after: ['variant' => $variant->id, 'platform' => $variant->platform->value, 'kind' => $kind, 'reason' => mb_substr($reason, 0, 300)], result: 'failure');

        if ($post !== null) {
            $this->syncPostStatus($post);
        }
    }

    private function toManualExport(SocialPostVariant $variant, string $reason): void
    {
        $variant->forceFill(['status' => SocialVariantStatus::ReadyForExport, 'last_error' => mb_substr($reason, 0, 1000)])->save();
        $post = $variant->post()->first();
        $this->audit->record(operation: 'social_post.ready_for_export', auditable: $post, actorType: ActorType::System, actorLabel: 'Publish queue', after: ['variant' => $variant->id, 'platform' => $variant->platform->value, 'reason' => mb_substr($reason, 0, 300)]);

        if ($post !== null) {
            $this->syncPostStatus($post);
        }
    }

    /**
     * Derive the post status from its variants once publishing started: published / posted manually when every
     * variant is out, failed when something failed and nothing is still moving, otherwise publishing (which
     * includes variants waiting for a manual export).
     */
    public function syncPostStatus(SocialPost $post): void
    {
        $post = $post->fresh() ?? $post;

        if (! in_array($post->status, [SocialPostStatus::Publishing, SocialPostStatus::Failed, SocialPostStatus::Published, SocialPostStatus::ExportedManually], true)) {
            return;
        }

        $statuses = $post->variants()->pluck('status')->map(fn ($status) => $status instanceof SocialVariantStatus ? $status : SocialVariantStatus::from($status));
        $active = $statuses->reject(fn (SocialVariantStatus $status): bool => $status === SocialVariantStatus::Cancelled);

        $next = match (true) {
            $active->isEmpty() => $post->status,
            $active->every(fn (SocialVariantStatus $status): bool => $status === SocialVariantStatus::ExportedManually) => SocialPostStatus::ExportedManually,
            $active->every(fn (SocialVariantStatus $status): bool => in_array($status, [SocialVariantStatus::Published, SocialVariantStatus::ExportedManually], true)) => SocialPostStatus::Published,
            $active->contains(SocialVariantStatus::Failed) && $active->doesntContain(fn (SocialVariantStatus $status): bool => in_array($status, [SocialVariantStatus::Queued, SocialVariantStatus::Publishing], true)) => SocialPostStatus::Failed,
            default => SocialPostStatus::Publishing,
        };

        $post->forceFill([
            'status' => $next,
            'published_at' => in_array($next, [SocialPostStatus::Published, SocialPostStatus::ExportedManually], true) ? ($post->published_at ?? now()) : $post->published_at,
        ])->save();
    }
}
