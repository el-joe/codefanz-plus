@php
    $sortLink = function (string $column) use ($sort, $direction) {
        $next = $sort === $column && $direction === 'desc' ? 'asc' : 'desc';

        return route('admin.leads.index', [...request()->except(['page', 'sort', 'direction']), 'sort' => $column, 'direction' => $next]);
    };
    $ariaSort = fn (string $column) => $sort === $column ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none';
@endphp

<x-layouts.admin :title="__('Leads')" :crumb="__('CRM & Delivery')">
    @include('admin.leads._tabs')

    <form method="GET" action="{{ route('admin.leads.index') }}" class="grid grid-cols-1 gap-3 rounded-xl border border-line bg-surface p-4 sm:grid-cols-2 lg:grid-cols-6" role="search" aria-label="{{ __('Filter leads') }}">
        @foreach (['follow_up', 'sort', 'direction'] as $keep)
            @if (filled($filters[$keep] ?? null))
                <input type="hidden" name="{{ $keep }}" value="{{ $filters[$keep] }}">
            @endif
        @endforeach
        <div class="lg:col-span-2">
            <x-ui.input name="q" type="search" :label="__('Search')" :value="$filters['q'] ?? null" :placeholder="__('Name, email, company or reference')" />
        </div>
        <x-ui.select name="status" :label="__('Status')" :options="\App\Enums\LeadStatus::options()" :value="$filters['status'] ?? null" :placeholder="__('All')" />
        <x-ui.select name="source" :label="__('Source')" :options="\App\Enums\LeadSource::options()" :value="$filters['source'] ?? null" :placeholder="__('All')" />
        <x-ui.select name="assignee" :label="__('Owner')" :options="['me' => __('Me'), 'none' => __('Unassigned')] + $assignees->all()" :value="$filters['assignee'] ?? null" :placeholder="__('Anyone')" />
        <div class="grid grid-cols-2 gap-2">
            <x-ui.input name="from" type="date" :label="__('From')" :value="$filters['from'] ?? null" />
            <x-ui.input name="to" type="date" :label="__('To')" :value="$filters['to'] ?? null" />
        </div>
        <div class="flex items-end gap-2 lg:col-span-6">
            <x-ui.button size="sm">{{ __('Apply filters') }}</x-ui.button>
            <a href="{{ route('admin.leads.index') }}" class="px-2 py-1.5 text-sm font-semibold text-fg-brand hover:underline">{{ __('Reset') }}</a>
        </div>
    </form>

    <x-ui.card padding="p-0" class="overflow-x-auto">
        <div class="flex items-center justify-between gap-3 px-4 py-3">
            <h2 class="text-lg font-semibold text-fg">{{ __('Leads') }}</h2>
            <p class="text-sm text-fg-tertiary">{{ __(':count total', ['count' => number_format($leads->total())]) }}</p>
        </div>
        @if ($leads->isEmpty())
            <x-ui.empty-state :title="__('No leads match')" :description="__('Leads arrive from the quote and contact forms, or can be added by hand.')" />
        @else
            <table class="w-full text-sm">
                <thead class="bg-subtle text-xs text-fg-tertiary">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-start font-semibold" aria-sort="{{ $ariaSort('name') }}"><a href="{{ $sortLink('name') }}" class="hover:text-fg">{{ __('Lead') }}</a></th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Source') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Status') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold" aria-sort="{{ $ariaSort('estimated_value_minor') }}"><a href="{{ $sortLink('estimated_value_minor') }}" class="hover:text-fg">{{ __('Est. value') }}</a></th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Owner') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold" aria-sort="{{ $ariaSort('follow_up_at') }}"><a href="{{ $sortLink('follow_up_at') }}" class="hover:text-fg">{{ __('Follow-up') }}</a></th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold" aria-sort="{{ $ariaSort('created_at') }}"><a href="{{ $sortLink('created_at') }}" class="hover:text-fg">{{ __('Received') }}</a></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($leads as $lead)
                        <tr class="border-t border-line-subtle" data-lead-row="{{ $lead->ulid }}">
                            <td class="px-4 py-3">
                                <a href="{{ route('admin.leads.show', $lead) }}" class="font-semibold text-fg hover:text-fg-brand" dir="auto">{{ $lead->name }}</a>
                                <p class="text-xs text-fg-tertiary"><span dir="ltr">{{ $lead->reference }}</span>@if ($lead->company) · <span dir="auto">{{ $lead->company }}</span>@endif</p>
                                @if ($lead->possible_duplicate_of_id)
                                    <x-ui.badge tone="warning" :dot="false" class="mt-1">{{ __('Possible duplicate') }}</x-ui.badge>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-fg-secondary">{{ $lead->source->label() }}</td>
                            <td class="px-4 py-3"><x-ui.badge :tone="$lead->status->tone()">{{ $lead->status->label() }}</x-ui.badge></td>
                            <td class="px-4 py-3 text-fg" dir="ltr">{{ $lead->formattedEstimatedValue() ?? '—' }}</td>
                            <td class="px-4 py-3 text-fg-secondary">{{ $lead->assignee?->name ?? __('Unassigned') }}</td>
                            <td class="px-4 py-3 {{ $lead->follow_up_at?->isPast() && $lead->status->isOpen() ? 'font-semibold text-fg-danger' : 'text-fg-secondary' }}">{{ $lead->follow_up_at?->translatedFormat('j M Y, H:i') ?? '—' }}</td>
                            <td class="px-4 py-3 text-fg-secondary">{{ $lead->created_at->diffForHumans() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-ui.card>

    {{ $leads->links() }}
</x-layouts.admin>
