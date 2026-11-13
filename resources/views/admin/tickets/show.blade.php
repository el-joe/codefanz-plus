<x-layouts.admin :title="$ticket->reference" :crumb="__('Support tickets')">
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
        <div class="flex min-w-0 flex-col gap-6">
            <x-ui.card>
                <div class="flex flex-wrap items-center gap-2">
                    <x-ui.badge :tone="$ticket->priority->tone()" :dot="false">{{ $ticket->priority->label() }}</x-ui.badge>
                    <x-ui.badge :tone="$ticket->status->tone()">{{ $ticket->status->label() }}</x-ui.badge>
                    <span class="text-xs text-fg-tertiary">{{ $ticket->category->label() }}</span>
                    <div class="flex-1"></div>
                    <a href="{{ route('admin.tickets.index') }}" class="text-sm font-semibold text-fg-brand hover:underline">{{ __('Back to tickets') }}</a>
                </div>
                <h2 class="mt-3 text-xl font-semibold text-fg" dir="auto">{{ $ticket->subject }}</h2>
                <p class="mt-1 text-sm text-fg-tertiary">
                    <span dir="auto">{{ $ticket->customer->company_name }}</span>
                    @if ($ticket->project) · <a href="{{ route('admin.projects.show', $ticket->project) }}" class="text-fg-brand hover:underline" dir="auto">{{ $ticket->project->name }}</a>@endif
                    · {{ __('Opened :date by :name', ['date' => $ticket->created_at->translatedFormat('j M Y, H:i'), 'name' => $ticket->opener?->name ?? __('Former user')]) }}
                </p>
            </x-ui.card>

            <ol class="flex flex-col gap-3" data-ticket-replies>
                @foreach ($ticket->replies as $reply)
                    <li class="rounded-xl border p-4 {{ $reply->is_internal ? 'border-line-danger/40 bg-danger-subtle/30' : ($reply->from_client ? 'border-line bg-surface' : 'border-brand/30 bg-brand-subtle/40') }}" data-internal="{{ $reply->is_internal ? 'true' : 'false' }}">
                        <div class="mb-2 flex flex-wrap items-center gap-2 text-xs text-fg-tertiary">
                            <span class="font-semibold text-fg" dir="auto">{{ $reply->author?->name ?? __('Former user') }}</span>
                            <span>{{ $reply->from_client ? __('Client') : __('CodeFanz team') }}</span>
                            @if ($reply->is_internal)
                                <x-ui.badge tone="danger">{{ __('Internal note — never visible to client') }}</x-ui.badge>
                            @endif
                            <span class="ms-auto">{{ $reply->created_at->translatedFormat('j M Y, H:i') }}</span>
                        </div>
                        <p class="whitespace-pre-line text-sm text-fg" dir="auto">{{ $reply->body }}</p>
                    </li>
                @endforeach
            </ol>

            @if ($ticket->status !== \App\Enums\TicketStatus::Closed)
                <x-ui.card :title="__('Reply')">
                    <form method="POST" action="{{ route('admin.tickets.reply', $ticket) }}" class="flex flex-col gap-3">
                        @csrf
                        @include('admin._textarea', ['name' => 'body', 'id' => 'reply-body', 'label' => __('Message'), 'value' => null, 'rows' => 4, 'max' => config('codefanz.support.reply_max_length')])
                        <label class="flex items-center gap-2 text-sm text-fg"><input type="checkbox" name="internal" value="1" @checked(old('internal')) class="size-4 accent-brand"> {{ __('Internal note (the client never sees it)') }}</label>
                        <x-ui.button size="sm" class="w-fit">{{ __('Send') }}</x-ui.button>
                    </form>
                </x-ui.card>
            @else
                <x-ui.alert tone="info" :title="__('This ticket is closed')">{{ __('Closed tickets take no more replies. The customer can open a new ticket.') }}</x-ui.alert>
            @endif
        </div>

        <div class="flex flex-col gap-6">
            <x-ui.card :title="__('Manage')">
                <form method="POST" action="{{ route('admin.tickets.update', $ticket) }}" class="flex flex-col gap-4">
                    @csrf
                    @method('PUT')
                    <x-ui.select name="status" :label="__('Status')" :options="\App\Enums\TicketStatus::options()" :value="$ticket->status->value" />
                    <x-ui.select name="priority" :label="__('Priority')" :options="\App\Enums\TicketPriority::options()" :value="$ticket->priority->value" />
                    <x-ui.select name="assigned_to" :label="__('Assignee')" :options="$agents" :value="$ticket->assigned_to" :placeholder="__('Unassigned')" />
                    <x-ui.button size="sm">{{ __('Save') }}</x-ui.button>
                </form>
            </x-ui.card>

            <x-ui.card :title="__('Service level')">
                <dl class="flex flex-col divide-y divide-line-subtle text-sm">
                    @foreach ([
                        __('Opened') => $ticket->created_at,
                        __('First response') => $ticket->first_response_at,
                        __('Resolved') => $ticket->resolved_at,
                        __('Closed') => $ticket->closed_at,
                    ] as $label => $time)
                        <div class="flex justify-between gap-4 py-2"><dt class="text-fg-tertiary">{{ $label }}</dt><dd class="text-fg">{{ $time?->translatedFormat('j M Y, H:i') ?? '—' }}</dd></div>
                    @endforeach
                </dl>
                <p class="mt-3 text-xs text-fg-tertiary">{{ __('Resolved tickets close automatically after :days days without a reply.', ['days' => config('codefanz.support.auto_close_after_days')]) }}</p>
            </x-ui.card>

            <x-ui.card :title="__('Customer')">
                <p class="text-sm font-semibold text-fg" dir="auto">{{ $ticket->customer->company_name }}</p>
                <p class="text-xs text-fg-tertiary" dir="ltr">{{ $ticket->customer->reference }}</p>
                @if ($ticket->opener)
                    <p class="mt-2 text-sm text-fg-secondary"><span dir="auto">{{ $ticket->opener->name }}</span> · <span dir="ltr">{{ $ticket->opener->email }}</span></p>
                @endif
            </x-ui.card>
        </div>
    </div>
</x-layouts.admin>
