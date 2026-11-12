<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Social\SocialPostWorkflow;
use App\Http\Controllers\Controller;
use App\Models\SocialPost;
use App\Models\SocialPostVariant;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Workflow buttons of a social post, the calendar's reschedule endpoint and the manual export confirmation.
 * Policies gate each button; SocialPostWorkflow re-checks the state, approval and four-eyes rule.
 */
class SocialWorkflowController extends Controller
{
    public function submit(Request $request, SocialPost $post, SocialPostWorkflow $workflow): RedirectResponse
    {
        Gate::authorize('update', $post);

        $workflow->submit($post, $request->user());

        return back()->with('toast', __('Submitted for approval. Reviewers have been notified.'));
    }

    public function approve(Request $request, SocialPost $post, SocialPostWorkflow $workflow): RedirectResponse
    {
        Gate::authorize('review', $post);

        $post = $workflow->approve($post, $request->user());

        if ($request->filled('scheduled_for')) {
            $workflow->schedule($post, $request->user(), $this->scheduledFor($request));

            return back()->with('toast', __('Post approved and scheduled.'));
        }

        return back()->with('toast', __('Post approved. Schedule it or publish it now.'));
    }

    public function requestChanges(Request $request, SocialPost $post, SocialPostWorkflow $workflow): RedirectResponse
    {
        Gate::authorize('review', $post);

        $validated = $request->validate(['note' => ['required', 'string', 'min:3', 'max:2000']]);
        $workflow->requestChanges($post, $request->user(), $validated['note']);

        return back()->with('toast', __('Changes requested. The author has been notified.'));
    }

    public function schedule(Request $request, SocialPost $post, SocialPostWorkflow $workflow): RedirectResponse
    {
        Gate::authorize('review', $post);

        $workflow->schedule($post, $request->user(), $this->scheduledFor($request));

        return back()->with('toast', __('Post scheduled.'));
    }

    /**
     * Calendar drag-and-drop (JSON) and its keyboard / no-JS form. The time is read in the staff member's time zone.
     */
    public function reschedule(Request $request, SocialPost $post, SocialPostWorkflow $workflow): JsonResponse|RedirectResponse
    {
        Gate::authorize('review', $post);

        $post = $workflow->schedule($post, $request->user(), $this->scheduledFor($request));
        $timezone = $request->user()->timezone ?: config('app.timezone');
        $local = $post->scheduled_at->timezone($timezone);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('Rescheduled to :date.', ['date' => $local->translatedFormat('j M Y · H:i')]),
                'post' => [
                    'id' => $post->ulid,
                    'status' => $post->status->value,
                    'scheduled_at' => $post->scheduled_at->toIso8601String(),
                    'local_date' => $local->format('Y-m-d'),
                    'local_time' => $local->format('H:i'),
                ],
            ]);
        }

        return back()->with('toast', __('Rescheduled to :date.', ['date' => $local->translatedFormat('j M Y · H:i')]));
    }

    public function unschedule(Request $request, SocialPost $post, SocialPostWorkflow $workflow): RedirectResponse
    {
        Gate::authorize('review', $post);

        $workflow->unschedule($post, $request->user());

        return back()->with('toast', __('Schedule removed. The post stays approved.'));
    }

    public function publishNow(Request $request, SocialPost $post, SocialPostWorkflow $workflow): RedirectResponse
    {
        Gate::authorize('review', $post);

        $workflow->publishNow($post, $request->user());

        return back()->with('toast', __('Publishing started. Accounts without API access are ready for manual export.'));
    }

    public function cancel(Request $request, SocialPost $post, SocialPostWorkflow $workflow): RedirectResponse
    {
        Gate::authorize('update', $post);

        $workflow->cancel($post, $request->user());

        return back()->with('toast', __('Post cancelled.'));
    }

    public function retry(Request $request, SocialPostVariant $variant, SocialPostWorkflow $workflow): RedirectResponse
    {
        $variant->load('post');
        Gate::authorize('review', $variant->post);

        $workflow->retry($variant, $request->user());

        return back()->with('toast', __('Sent back to the publish queue.'));
    }

    public function posted(Request $request, SocialPostVariant $variant, SocialPostWorkflow $workflow): RedirectResponse
    {
        $variant->load('post');
        Gate::authorize('update', $variant->post);

        $validated = $request->validate(['published_url' => ['required', 'url:https', 'max:500']]);
        $workflow->markPostedManually($variant, $request->user(), $validated['published_url']);

        return back()->with('toast', __('Marked as posted manually.'));
    }

    private function scheduledFor(Request $request): CarbonImmutable
    {
        $validated = $request->validate(['scheduled_for' => ['required', 'date_format:Y-m-d\TH:i']]);
        $timezone = $request->user()->timezone ?: config('app.timezone');
        $when = CarbonImmutable::createFromFormat('Y-m-d\TH:i', $validated['scheduled_for'], $timezone);

        if ($when === null) {
            throw ValidationException::withMessages(['scheduled_for' => __('Choose a valid date and time.')]);
        }

        return $when->utc();
    }
}
