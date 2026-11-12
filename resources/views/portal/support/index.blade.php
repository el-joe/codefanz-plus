<x-layouts.portal :title="__('Support tickets')" :crumb="__('Support')">
    <div class="flex flex-wrap items-center justify-between gap-3">
        @include('portal._tabs', ['label' => __('Filter tickets'), 'tabs' => [
            [__('Open (:count)', ['count' => $counts['open']]), route('portal.support.index'), $tab === 'open'],
            [__('Waiting on you (:count)', ['count' => $counts['waiting']]), route('portal.support.index', ['status' => 'waiting']), $tab === 'waiting'],
            [__('Resolved'), route('portal.support.index', ['status' => 'resolved']), $tab === 'resolved'],
            [__('All'), route('portal.support.index', ['status' => 'all']), $tab === 'all'],
        ]])
        <x-ui.button :href="route('portal.support.create')">{{ __('New ticket') }}</x-ui.button>
    </div>

    @if ($tickets->isEmpty())
        <x-ui.card padding="p-6">
            @include('portal._empty-module', ['title' => __('No support tickets here'), 'description' => __('Something not working or need a change? Open a ticket and the support team will reply.'), 'icon' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/>'])
        </x-ui.card>
    @else
        <x-ui.card padding="p-0">
            <ul class="divide-y divide-line-subtle" data-tickets>
                @foreach ($tickets as $ticket)
                    <li>
                        <a href="{{ route('portal.support.show', $ticket) }}" class="flex items-center gap-3 px-5 py-4 hover:bg-subtle" data-ticket="{{ $ticket->ulid }}">
                            <div class="min-w-0 flex-1">
                                <p class="flex flex-wrap items-center gap-2 text-xs text-fg-tertiary">
                                    <span class="font-mono" dir="ltr">{{ $ticket->reference }}</span>
                                    <x-ui.badge :tone="$ticket->priority->tone()" :dot="false">{{ $ticket->priority->label() }}</x-ui.badge>
                                </p>
                                <p class="mt-1 truncate text-sm font-semibold text-fg" dir="auto">{{ $ticket->subject }}</p>
                                <p class="mt-1 flex flex-wrap items-center gap-2 text-xs text-fg-tertiary">
                                    <x-ui.badge :tone="$ticket->status->tone()">{{ $ticket->status->clientLabel() }}</x-ui.badge>
                                    {{ __('Updated :time', ['time' => ($ticket->last_activity_at ?? $ticket->created_at)->diffForHumans()]) }}
                                    @if ($ticket->project) · <span dir="auto">{{ $ticket->project->name }}</span>@endif
                                </p>
                            </div>
                            <span aria-hidden="true" class="text-fg-tertiary rtl:-scale-x-100">›</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </x-ui.card>
        {{ $tickets->links() }}
    @endif
</x-layouts.portal>
