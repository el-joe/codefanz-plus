<?php

namespace App\Http\Controllers\Portal;

use App\Actions\Projects\PostConversationMessage;
use App\Actions\Projects\StartConversation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreConversationMessageRequest;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Project;
use App\Services\CurrentCustomer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Conversations (P11): thread list with unread counts, thread view, new thread, post and the JSON endpoint the
 * thread polls every ~20 s. Threads are resolved inside the current customer, so foreign threads are a 404.
 */
class ConversationController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $conversations = $this->threads($request);

        if ($conversations->isNotEmpty() && ! $request->boolean('list')) {
            return redirect()->route('portal.conversations.show', $conversations->first());
        }

        return view('portal.conversations.index', [
            'conversations' => $conversations,
            'active' => null,
            'projects' => $this->projects(),
        ]);
    }

    public function show(Request $request, string $conversation): View
    {
        $active = $this->find($conversation);
        $active->load(['project:id,ulid,name', 'messages' => fn ($query) => $query->with(['author:id,name', 'attachments'])]);
        $active->markReadBy($request->user());

        return view('portal.conversations.index', [
            'conversations' => $this->threads($request),
            'active' => $active,
            'projects' => $this->projects(),
        ]);
    }

    public function store(StoreConversationMessageRequest $request, StartConversation $start, PostConversationMessage $post): RedirectResponse
    {
        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:160'],
            'project' => ['required', 'string', Rule::exists('projects', 'ulid')->where('customer_id', app(CurrentCustomer::class)->id())->whereNull('deleted_at')],
        ]);
        $project = Project::query()->forCurrentCustomer()->where('ulid', $validated['project'])->firstOrFail();

        $conversation = $start->handle($project, $validated['subject'], $request->user());
        $post->handle($conversation, $request->validated('body'), $request->user(), fromClient: true, attachment: $request->file('attachment'));

        return redirect()->route('portal.conversations.show', $conversation);
    }

    public function message(StoreConversationMessageRequest $request, string $conversation, PostConversationMessage $post): RedirectResponse|JsonResponse
    {
        $conversation = $this->find($conversation);
        $message = $post->handle($conversation, $request->validated('body'), $request->user(), fromClient: true, attachment: $request->file('attachment'));

        if ($request->expectsJson()) {
            $message->load(['author:id,name', 'attachments']);

            return response()->json(['message' => $message->threadPayload($request->user(), forPortal: true)], 201);
        }

        return redirect()->route('portal.conversations.show', $conversation);
    }

    public function messages(Request $request, string $conversation): JsonResponse
    {
        $conversation = $this->find($conversation);
        $after = (int) ($request->validate(['after' => ['nullable', 'integer', 'min:0']])['after'] ?? 0);

        $messages = $conversation->messages()->with(['author:id,name', 'attachments'])->where('id', '>', $after)->limit(100)->get();
        $conversation->markReadBy($request->user());

        return response()->json([
            'messages' => $messages->map(fn (ConversationMessage $message): array => $message->threadPayload($request->user(), forPortal: true))->all(),
        ]);
    }

    private function find(string $ulid): Conversation
    {
        return Conversation::query()->forCurrentCustomer()->where('ulid', $ulid)->whereHas('project')->firstOrFail();
    }

    /**
     * @return Collection<int, Conversation>
     */
    private function threads(Request $request): Collection
    {
        return Conversation::query()
            ->forCurrentCustomer()
            ->whereHas('project')
            ->with(['project:id,ulid,name', 'latestMessage.author:id,name'])
            ->withUnreadCountFor($request->user())
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return SupportCollection<string, string>
     */
    private function projects(): SupportCollection
    {
        return Project::query()->forCurrentCustomer()->orderBy('name')->pluck('name', 'ulid');
    }
}
