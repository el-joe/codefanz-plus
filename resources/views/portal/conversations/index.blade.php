<x-layouts.portal :title="__('Conversations')" :crumb="__('Conversations')">
    @if ($conversations->isEmpty())
        <x-ui.card padding="p-6">
            @include('portal._empty-module', ['title' => __('No messages yet'), 'description' => __('Project conversations with your CodeFanz team will appear here.'), 'icon' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>'])
        </x-ui.card>
    @else
        <x-ui.card padding="p-0" class="overflow-hidden">
            <div class="flex flex-col lg:flex-row">
                <div class="border-b border-line lg:w-80 lg:shrink-0 lg:border-e lg:border-b-0 {{ $active ? 'hidden lg:block' : '' }}">
                    <ul class="flex flex-col" data-conversation-list>
                        @foreach ($conversations as $conversation)
                            @php($isActive = $active && $conversation->is($active))
                            <li>
                                <a href="{{ route('portal.conversations.show', $conversation) }}" @if ($isActive) aria-current="page" @endif data-conversation-link="{{ $conversation->ulid }}"
                                   class="flex flex-col gap-0.5 border-s-4 px-4 py-3 {{ $isActive ? 'border-brand bg-surface' : 'border-transparent hover:bg-subtle' }}">
                                    <span class="flex items-center gap-2">
                                        <span class="flex-1 truncate text-sm font-semibold text-fg" dir="auto">{{ $conversation->project->name }} · {{ $conversation->displaySubject() }}</span>
                                        @if ($conversation->last_message_at)
                                            <span class="text-xs text-fg-tertiary">{{ $conversation->last_message_at->isToday() ? $conversation->last_message_at->format('H:i') : $conversation->last_message_at->translatedFormat('j M') }}</span>
                                        @endif
                                    </span>
                                    <span class="flex items-center gap-2">
                                        <span class="flex-1 truncate text-xs text-fg-secondary" dir="auto">
                                            @if ($conversation->latestMessage)
                                                {{ $conversation->latestMessage->user_id === auth()->id() ? __('You') : ($conversation->latestMessage->author?->name ?? __('CodeFanz team')) }}: {{ \Illuminate\Support\Str::limit($conversation->latestMessage->body, 60) }}
                                            @else
                                                {{ __('No messages yet') }}
                                            @endif
                                        </span>
                                        @if ($conversation->unread_count > 0 && ! $isActive)
                                            <span class="rounded-full bg-brand-subtle px-2 py-0.5 text-xs font-semibold text-fg-brand" data-unread="{{ $conversation->unread_count }}">{{ $conversation->unread_count }}<span class="sr-only"> {{ __('unread') }}</span></span>
                                        @endif
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                    <details class="border-t border-line px-4 py-3">
                        <summary class="cursor-pointer text-sm font-semibold text-fg-brand">{{ __('New conversation') }}</summary>
                        <form method="POST" action="{{ route('portal.conversations.store') }}" class="mt-3 flex flex-col gap-3">
                            @csrf
                            <x-ui.select name="project" :label="__('Project')" :options="$projects" required />
                            <x-ui.input name="subject" :label="__('Subject')" maxlength="160" required dir="auto" />
                            <div class="flex flex-col gap-1.5">
                                <label for="new-conversation-body" class="text-sm font-semibold text-fg">{{ __('Message') }}</label>
                                <textarea id="new-conversation-body" name="body" rows="3" required maxlength="{{ config('codefanz.projects.message_max_length') }}" dir="auto" class="rounded-md border border-line-strong bg-surface px-3 py-2 text-sm text-fg"></textarea>
                            </div>
                            <x-ui.button size="sm" class="w-fit">{{ __('Send') }}</x-ui.button>
                        </form>
                    </details>
                </div>

                @if ($active)
                    <div class="flex min-w-0 flex-1 flex-col">
                        <a href="{{ route('portal.conversations.index', ['list' => 1]) }}" class="flex items-center gap-1 border-b border-line px-4 py-2 text-sm font-semibold text-fg-brand lg:hidden"><span aria-hidden="true" class="rtl:-scale-x-100">←</span> {{ __('All conversations') }}</a>
                        <x-conversation.thread
                            :messages="$active->messages->map(fn ($message) => $message->threadPayload(auth()->user(), forPortal: true))->all()"
                            :poll-url="route('portal.conversations.messages.index', $active)"
                            :post-url="route('portal.conversations.messages.store', $active)"
                            :title="$active->project->name.' · '.$active->displaySubject()"
                            :subtitle="__('Your CodeFanz project team and the people in your account can read this thread.')"
                            :poll-seconds="config('codefanz.projects.poll_seconds')"
                            :max-length="config('codefanz.projects.message_max_length')" />
                    </div>
                @else
                    <div class="hidden flex-1 items-center justify-center p-8 text-sm text-fg-secondary lg:flex">{{ __('Choose a conversation.') }}</div>
                @endif
            </div>
        </x-ui.card>
    @endif
</x-layouts.portal>
