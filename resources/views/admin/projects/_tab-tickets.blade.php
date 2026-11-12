<x-ui.card :title="__('Support tickets')">
    @cannot('viewAny', \App\Models\SupportTicket::class)
        <p class="text-sm text-fg-secondary">{{ __('You need the support permission to see tickets.') }}</p>
    @else
        @if ($tickets->isEmpty())
            <x-ui.empty-state :title="__('No tickets for this project')" :description="__('Clients open tickets from the portal.')" />
        @else
            <ul class="flex flex-col divide-y divide-line-subtle">
                @foreach ($tickets as $ticket)
                    <li class="flex flex-wrap items-center gap-3 py-3">
                        <span class="font-mono text-xs text-fg-tertiary" dir="ltr">{{ $ticket->reference }}</span>
                        <a href="{{ route('admin.tickets.show', $ticket) }}" class="min-w-0 flex-1 truncate text-sm font-semibold text-fg hover:text-fg-brand" dir="auto">{{ $ticket->subject }}</a>
                        <x-ui.badge :tone="$ticket->priority->tone()" :dot="false">{{ $ticket->priority->label() }}</x-ui.badge>
                        <x-ui.badge :tone="$ticket->status->tone()">{{ $ticket->status->label() }}</x-ui.badge>
                        <span class="text-xs text-fg-tertiary" dir="auto">{{ $ticket->assignee?->name ?? __('Unassigned') }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    @endcannot
</x-ui.card>
