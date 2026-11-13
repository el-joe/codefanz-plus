<x-layouts.portal :title="$ticket->reference" :crumb="__('Support')">
    <a href="{{ route('portal.support.index') }}" class="flex w-fit items-center gap-1 text-sm font-semibold text-fg-brand hover:underline"><span aria-hidden="true" class="rtl:-scale-x-100">←</span> {{ __('All tickets') }}</a>

    <x-ui.card class="max-w-4xl">
        <div class="flex flex-wrap items-center gap-2">
            <span class="font-mono text-xs text-fg-tertiary" dir="ltr">{{ $ticket->reference }}</span>
            <x-ui.badge :tone="$ticket->priority->tone()" :dot="false">{{ $ticket->priority->label() }}</x-ui.badge>
            <x-ui.badge :tone="$ticket->status->tone()">{{ $ticket->status->clientLabel() }}</x-ui.badge>
        </div>
        <h2 class="mt-2 text-xl font-semibold text-fg" dir="auto">{{ $ticket->subject }}</h2>
        <p class="mt-1 text-xs text-fg-tertiary">
            @if ($ticket->project)<span dir="auto">{{ $ticket->project->name }}</span> · @endif
            {{ __('Opened :date', ['date' => $ticket->created_at->translatedFormat('j M Y')]) }}
            @if ($ticket->assignee) · {{ __('Assigned to :name', ['name' => $ticket->assignee->name]) }}@endif
        </p>

        <ol class="mt-6 flex flex-col gap-4" data-ticket-replies>
            @foreach ($ticket->publicReplies as $reply)
                @php($mine = $reply->from_client)
                <li class="flex {{ $mine ? 'justify-end' : 'justify-start' }}">
                    <div class="max-w-[85%] rounded-xl px-4 py-3 text-sm {{ $mine ? 'bg-brand text-fg-on-brand' : 'border border-line bg-surface text-fg' }}">
                        @unless ($mine)<p class="mb-1 text-xs font-semibold text-fg-brand" dir="auto">{{ $reply->author?->name ?? __('CodeFanz team') }}</p>@endunless
                        @if ($mine && $reply->user_id !== auth()->id())<p class="mb-1 text-xs font-semibold" dir="auto">{{ $reply->author?->name }}</p>@endif
                        <p class="whitespace-pre-line break-words" dir="auto">{{ $reply->body }}</p>
                        <p class="mt-1 text-[11px]">{{ $reply->created_at->translatedFormat('j M, H:i') }}</p>
                    </div>
                </li>
            @endforeach
        </ol>

        @if ($ticket->status === \App\Enums\TicketStatus::Closed)
            <x-ui.alert tone="info" :title="__('This ticket is closed')" class="mt-6">{{ __('Need more help? Open a new ticket.') }}</x-ui.alert>
        @else
            <form method="POST" action="{{ route('portal.support.reply', $ticket) }}" class="mt-6 flex flex-col gap-3">
                @csrf
                <label for="reply-body" class="text-sm font-semibold text-fg">{{ __('Reply') }}</label>
                <textarea id="reply-body" name="body" rows="4" required maxlength="{{ config('codefanz.support.reply_max_length') }}" dir="auto" placeholder="{{ __('Write your reply…') }}"
                          class="rounded-md border bg-surface px-3 py-2 text-sm text-fg {{ $errors->has('body') ? 'border-line-danger' : 'border-line-strong' }}">{{ old('body') }}</textarea>
                @error('body')<p class="text-sm text-fg-danger">{{ $message }}</p>@enderror
                <div class="flex flex-wrap items-center gap-4">
                    <x-ui.button size="sm">{{ __('Send reply') }}</x-ui.button>
                </div>
            </form>
            @if ($ticket->status->isOpen())
                <form method="POST" action="{{ route('portal.support.resolve', $ticket) }}" class="mt-3">
                    @csrf
                    <button type="submit" class="text-sm font-semibold text-fg-brand hover:underline">{{ __('Mark as resolved') }}</button>
                </form>
            @endif
        @endif
    </x-ui.card>
</x-layouts.portal>
