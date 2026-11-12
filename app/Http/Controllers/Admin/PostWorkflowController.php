<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Blog\PostWorkflow;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewPostRequest;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Editorial workflow buttons of the article editor (A11).
 */
class PostWorkflowController extends Controller
{
    public function submit(Request $request, Post $post, PostWorkflow $workflow): RedirectResponse
    {
        Gate::authorize('submit', $post);

        $workflow->submit($post, $request->user());

        return back()->with('toast', __('Submitted for review. Reviewers have been notified.'));
    }

    public function approve(ReviewPostRequest $request, Post $post, PostWorkflow $workflow): RedirectResponse
    {
        $post = $workflow->approve($post, $request->user());

        return match ($request->input('then', 'none')) {
            'publish' => $this->published($workflow->publish($post, $request->user())),
            'schedule' => $this->scheduled($workflow->schedule($post, $request->user(), $request->scheduledFor())),
            default => back()->with('toast', __('Article approved.')),
        };
    }

    public function requestChanges(Request $request, Post $post, PostWorkflow $workflow): RedirectResponse
    {
        Gate::authorize('review', $post);

        $validated = $request->validate(['note' => ['required', 'string', 'min:3', 'max:2000']]);
        $workflow->requestChanges($post, $request->user(), $validated['note']);

        return back()->with('toast', __('Changes requested. The author has been notified.'));
    }

    public function publish(ReviewPostRequest $request, Post $post, PostWorkflow $workflow): RedirectResponse
    {
        return $this->published($workflow->publish($post, $request->user()));
    }

    public function schedule(ReviewPostRequest $request, Post $post, PostWorkflow $workflow): RedirectResponse
    {
        return $this->scheduled($workflow->schedule($post, $request->user(), $request->scheduledFor()));
    }

    public function unschedule(Request $request, Post $post, PostWorkflow $workflow): RedirectResponse
    {
        Gate::authorize('review', $post);

        $workflow->unschedule($post, $request->user());

        return back()->with('toast', __('Schedule cancelled. The article stays approved.'));
    }

    public function archive(Request $request, Post $post, PostWorkflow $workflow): RedirectResponse
    {
        Gate::authorize('review', $post);

        $workflow->archive($post, $request->user());

        return back()->with('toast', __('Article archived. It is no longer visible on the site.'));
    }

    public function reopen(Request $request, Post $post, PostWorkflow $workflow): RedirectResponse
    {
        Gate::authorize('update', $post);

        $workflow->reopen($post, $request->user());

        return back()->with('toast', __('Article moved back to draft.'));
    }

    private function published(Post $post): RedirectResponse
    {
        return redirect()->route('admin.posts.edit', $post)->with('toast', __('Article published.'));
    }

    private function scheduled(Post $post): RedirectResponse
    {
        return redirect()->route('admin.posts.edit', $post)->with('toast', __('Article scheduled.'));
    }
}
