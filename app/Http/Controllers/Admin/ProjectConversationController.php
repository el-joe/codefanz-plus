<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Projects\PostConversationMessage;
use App\Actions\Projects\StartConversation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreConversationMessageRequest;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Staff side of project conversations: new thread, post a message, and the JSON endpoint the thread polls.
 */
class ProjectConversationController extends Controller
{
    public function store(StoreConversationMessageRequest $request, Project $project, StartConversation $start, PostConversationMessage $post): RedirectResponse
    {
        Gate::authorize('update', $project);
        $request->validate(['subject' => ['required']]);

        $conversation = $start->handle($project, $request->validated('subject'), $request->user());
        $post->handle($conversation, $request->validated('body'), $request->user(), fromClient: false, attachment: $request->file('attachment'));

        return redirect()->route('admin.projects.show', ['project' => $project, 'tab' => 'conversation', 'conversation' => $conversation->ulid]);
    }

    public function message(StoreConversationMessageRequest $request, Project $project, Conversation $conversation, PostConversationMessage $post): RedirectResponse|JsonResponse
    {
        Gate::authorize('update', $project);

        $message = $post->handle($conversation, $request->validated('body'), $request->user(), fromClient: false, attachment: $request->file('attachment'));

        if ($request->expectsJson()) {
            $message->load(['author:id,name', 'attachments']);

            return response()->json(['message' => $message->threadPayload($request->user(), forPortal: false)], 201);
        }

        return redirect()->route('admin.projects.show', ['project' => $project, 'tab' => 'conversation', 'conversation' => $conversation->ulid]);
    }

    /**
     * Messages newer than `after` (polled every ~20 s by resources/js/modules/conversation.js); marks them read.
     */
    public function messages(Request $request, Project $project, Conversation $conversation): JsonResponse
    {
        Gate::authorize('view', $project);

        $after = (int) ($request->validate(['after' => ['nullable', 'integer', 'min:0']])['after'] ?? 0);

        $messages = $conversation->messages()->with(['author:id,name', 'attachments'])->where('id', '>', $after)->limit(100)->get();
        $conversation->markReadBy($request->user());

        return response()->json([
            'messages' => $messages->map(fn (ConversationMessage $message): array => $message->threadPayload($request->user(), forPortal: false))->all(),
        ]);
    }
}
