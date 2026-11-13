<x-ui.card padding="p-0" class="overflow-hidden">
    <div class="flex flex-col lg:flex-row">
        <div class="border-b border-line lg:w-72 lg:shrink-0 lg:border-e lg:border-b-0">
            <ul class="flex flex-col" data-conversation-list>
                @foreach ($conversations as $conversation)
                    @php($isActive = $activeConversation && $conversation->is($activeConversation))
                    <li>
                        <a href="{{ route('admin.projects.show', ['project' => $project, 'tab' => 'conversation', 'conversation' => $conversation->ulid]) }}" @if ($isActive) aria-current="page" @endif
                           class="flex items-center gap-2 border-s-4 px-4 py-3 text-sm {{ $isActive ? 'border-brand bg-brand-subtle' : 'border-transparent hover:bg-subtle' }}">
                            <span class="flex-1 truncate font-semibold text-fg" dir="auto">{{ $conversation->displaySubject() }}</span>
                            @if ($conversation->unread_count > 0 && ! $isActive)
                                <span class="rounded-full bg-brand px-2 py-0.5 text-xs font-semibold text-fg-on-brand">{{ $conversation->unread_count }}<span class="sr-only"> {{ __('unread') }}</span></span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
            @if ($canManage)
                <details class="border-t border-line px-4 py-3">
                    <summary class="cursor-pointer text-sm font-semibold text-fg-brand">{{ __('New thread') }}</summary>
                    <form method="POST" action="{{ route('admin.projects.conversations.store', $project) }}" class="mt-3 flex flex-col gap-3">
                        @csrf
                        <x-ui.input name="subject" :label="__('Subject')" maxlength="160" required dir="auto" />
                        @include('admin._textarea', ['name' => 'body', 'id' => 'new-thread-body', 'label' => __('Message'), 'value' => null, 'rows' => 3, 'max' => config('codefanz.projects.message_max_length')])
                        <x-ui.button size="sm" class="w-fit">{{ __('Start thread') }}</x-ui.button>
                    </form>
                </details>
            @endif
        </div>

        @if ($activeConversation)
            <x-conversation.thread
                :messages="$activeConversation->messages->map(fn ($message) => $message->threadPayload(auth()->user(), forPortal: false))->all()"
                :poll-url="route('admin.projects.conversations.messages.index', [$project, $activeConversation])"
                :post-url="route('admin.projects.conversations.messages.store', [$project, $activeConversation])"
                :title="$activeConversation->displaySubject()"
                :subtitle="__('Visible to the client’s portal users and the project team.')"
                :poll-seconds="config('codefanz.projects.poll_seconds')"
                :max-length="config('codefanz.projects.message_max_length')"
                :can-post="$canManage" />
        @else
            <x-ui.empty-state :title="__('No conversations yet')" class="flex-1" />
        @endif
    </div>
</x-ui.card>
