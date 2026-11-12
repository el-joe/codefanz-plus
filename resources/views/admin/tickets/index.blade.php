@php
    $statusOptions = ['open_all' => __('All open'), ...\App\Enums\TicketStatus::options(), 'all' => __('All tickets')];
@endphp

<x-layouts.admin :title="__('Support tickets')" :crumb="__('Support')">
    <form method="GET" action="{{ route('admin.tickets.index') }}" class="flex flex-wrap items-end gap-3" role="search">
        <div class="w-full sm:w-64">
            <x-ui.input name="q" type="search" :label="__('Search')" :value="$filters['q'] ?? null" :placeholder="__('Subject, customer or reference')" />
        </div>
        <div class="w-full sm:w-44">
            <x-ui.select name="status" :label="__('Status')" :value="$status" :options="$statusOptions" />
        </div>
        <div class="w-full sm:w-40">
            <x-ui.select name="priority" :label="__('Priority')" :value="$filters['priority'] ?? null" :placeholder="__('Any priority')" :options="\App\Enums\TicketPriority::options()" />
        </div>
        <div class="w-full sm:w-44">
            <x-ui.select name="assignee" :label="__('Assignee')" :value="$filters['assignee'] ?? null" :placeholder="__('Anyone')" :options="['me' => __('Me'), 'none' => __('Unassigned')] + $agents->all()" />
        </div>
        <x-ui.button variant="secondary">{{ __('Filter') }}</x-ui.button>
    </form>

    <x-ui.card padding="p-0" class="overflow-x-auto">
        @if ($tickets->isEmpty())
            <x-ui.empty-state :title="__('No tickets here')" :description="__('Clients open tickets from the portal. Nothing matches these filters.')" />
        @else
            <table class="w-full text-sm">
                <thead class="bg-subtle text-xs text-fg-tertiary">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Ticket') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Customer') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Priority') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Status') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Assignee') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('First response') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Last activity') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($tickets as $ticket)
                        <tr class="border-t border-line-subtle">
                            <td class="px-4 py-3">
                                <a href="{{ route('admin.tickets.show', $ticket) }}" class="font-semibold text-fg hover:text-fg-brand" dir="auto">{{ $ticket->subject }}</a>
                                <p class="text-xs text-fg-tertiary"><span dir="ltr">{{ $ticket->reference }}</span> · {{ $ticket->category->label() }}@if ($ticket->project) · <span dir="auto">{{ $ticket->project->name }}</span>@endif</p>
                            </td>
                            <td class="px-4 py-3 text-fg-secondary" dir="auto">{{ $ticket->customer?->company_name }}</td>
                            <td class="px-4 py-3"><x-ui.badge :tone="$ticket->priority->tone()" :dot="false">{{ $ticket->priority->label() }}</x-ui.badge></td>
                            <td class="px-4 py-3"><x-ui.badge :tone="$ticket->status->tone()">{{ $ticket->status->label() }}</x-ui.badge></td>
                            <td class="px-4 py-3 text-fg-secondary" dir="auto">{{ $ticket->assignee?->name ?? __('Unassigned') }}</td>
                            <td class="px-4 py-3 text-fg-secondary">{{ $ticket->first_response_at ? $ticket->created_at->diffForHumans($ticket->first_response_at, \Carbon\CarbonInterface::DIFF_ABSOLUTE) : __('Waiting') }}</td>
                            <td class="px-4 py-3 text-fg-secondary">{{ ($ticket->last_activity_at ?? $ticket->created_at)->diffForHumans() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-ui.card>

    {{ $tickets->links() }}
</x-layouts.admin>
