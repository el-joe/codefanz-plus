@props(['messages', 'pollUrl', 'postUrl', 'title', 'subtitle' => null, 'pollSeconds' => 20, 'maxLength' => 5000, 'canPost' => true])

{{--
    Project conversation thread shared by the admin and the portal (Figma P11). $messages are
    ConversationMessage::threadPayload() arrays. resources/js/modules/conversation.js polls $pollUrl every
    $pollSeconds for newer messages and renders them with textContent (plain text only), and posts the
    composer with fetch; without JavaScript the composer is a normal form post.
--}}
<section class="flex min-h-[28rem] min-w-0 flex-1 flex-col" data-conversation data-poll-url="{{ $pollUrl }}" data-poll-seconds="{{ $pollSeconds }}"
         data-after="{{ collect($messages)->max('id') ?? 0 }}" data-error-text="{{ __('Message could not be sent. Please try again.') }}">
    <header class="border-b border-line px-5 py-4">
        <h2 class="text-base font-semibold text-fg" dir="auto">{{ $title }}</h2>
        @if ($subtitle)
            <p class="text-xs text-fg-tertiary" dir="auto">{{ $subtitle }}</p>
        @endif
    </header>

    <ol class="flex max-h-[60vh] flex-1 flex-col gap-4 overflow-y-auto bg-canvas px-5 py-5" data-conversation-messages aria-live="polite">
        @forelse ($messages as $message)
            @include('components.conversation._message', ['message' => $message])
        @empty
            <li class="m-auto text-sm text-fg-secondary" data-conversation-empty>{{ __('No messages yet. Say hello to start the conversation.') }}</li>
        @endforelse
    </ol>

    <template data-conversation-template>
        @include('components.conversation._message', ['message' => ['id' => 0, 'mine' => false, 'author' => '', 'initials' => '', 'body' => '', 'time' => '', 'attachments' => []], 'template' => true])
    </template>

    @if ($canPost)
        <form method="POST" action="{{ $postUrl }}" enctype="multipart/form-data" class="flex flex-col gap-2 border-t border-line px-4 py-3" data-conversation-form>
            @csrf
            <label for="conversation-body" class="sr-only">{{ __('Message') }}</label>
            <div class="flex items-end gap-2">
                <label class="flex size-10 shrink-0 cursor-pointer items-center justify-center rounded-md text-fg-secondary hover:bg-subtle" title="{{ __('Attach a file') }}">
                    <span class="sr-only">{{ __('Attach a file') }}</span>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m17 8-5-5-5 5"/><path d="M12 3v12"/></svg>
                    <input type="file" name="attachment" class="sr-only" data-conversation-file accept="{{ collect(config('codefanz.leads.attachments.extensions'))->map(fn ($extension) => '.'.$extension)->implode(',') }}">
                </label>
                <textarea id="conversation-body" name="body" rows="2" required maxlength="{{ $maxLength }}" dir="auto" placeholder="{{ __('Write a message…') }}"
                          class="min-h-10 flex-1 rounded-md border border-line-strong bg-subtle px-3 py-2 text-sm text-fg focus:border-line-focus focus:outline-none focus:ring-4 focus:ring-brand/20">{{ old('body') }}</textarea>
                <x-ui.button class="shrink-0" aria-label="{{ __('Send') }}">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="rtl:-scale-x-100"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
                    <span class="hidden sm:inline">{{ __('Send') }}</span>
                </x-ui.button>
            </div>
            <p class="text-xs text-fg-tertiary" data-conversation-file-name></p>
            @error('body')<p class="text-sm text-fg-danger">{{ $message }}</p>@enderror
            @error('attachment')<p class="text-sm text-fg-danger">{{ $message }}</p>@enderror
            <p class="hidden text-sm text-fg-danger" data-conversation-error role="alert"></p>
        </form>
    @endif
</section>
