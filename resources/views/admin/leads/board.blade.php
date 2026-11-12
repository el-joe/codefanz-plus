<x-layouts.admin :title="__('Leads')" :crumb="__('CRM & Delivery')">
    @include('admin.leads._tabs')

    <form method="GET" action="{{ route('admin.leads.board') }}" class="flex flex-wrap items-end gap-3" role="search" aria-label="{{ __('Filter leads') }}">
        <div class="min-w-56 flex-1">
            <x-ui.input name="q" type="search" :label="__('Search')" :value="$filters['q'] ?? null" :placeholder="__('Name, email, company or reference')" />
        </div>
        <x-ui.select name="source" :label="__('Source')" :options="\App\Enums\LeadSource::options()" :value="$filters['source'] ?? null" :placeholder="__('All')" />
        <x-ui.select name="assignee" :label="__('Owner')" :options="['me' => __('Me'), 'none' => __('Unassigned')] + $assignees->all()" :value="$filters['assignee'] ?? null" :placeholder="__('Anyone')" />
        <x-ui.button size="sm" variant="secondary">{{ __('Apply filters') }}</x-ui.button>
    </form>

    @php($canMove = auth()->user()->can('create', \App\Models\Lead::class))

    <div class="flex gap-4 overflow-x-auto pb-4" data-lead-board
         data-lost-prompt="{{ __('Why was this lead lost?') }}" data-error-message="{{ __('The move could not be saved. The card was put back.') }}">
        @foreach ($columns as $value => $column)
            <section class="flex w-72 shrink-0 flex-col gap-3 rounded-xl bg-subtle p-3" data-lead-column="{{ $value }}" data-lead-column-label="{{ $column['status']->label() }}" aria-labelledby="column-{{ $value }}">
                <h2 id="column-{{ $value }}" class="flex items-center gap-2 px-1 text-sm font-semibold text-fg">
                    <x-ui.badge :tone="$column['status']->tone()" data-lead-count>{{ $column['total'] }}</x-ui.badge>
                    {{ $column['status']->label() }}
                    @unless ($column['status']->isOpen())
                        <span class="text-xs font-normal text-fg-tertiary">{{ __('(last 30 days)') }}</span>
                    @endunless
                </h2>
                <ul class="flex min-h-24 flex-col gap-3" data-lead-list>
                    @foreach ($column['leads'] as $lead)
                        <li class="flex flex-col gap-2 rounded-lg border border-line bg-surface p-3 shadow-sm"
                            data-lead-card data-status="{{ $lead->status->value }}" data-lock-version="{{ $lead->lock_version }}"
                            data-move-url="{{ route('admin.leads.status', $lead) }}" @if ($canMove && $lead->status->allowedTransitions()) draggable="true" @endif>
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-xs text-fg-tertiary" dir="ltr">{{ $lead->reference }}</span>
                                <x-ui.badge :tone="$lead->priority->tone()">{{ $lead->priority->label() }}</x-ui.badge>
                            </div>
                            <a href="{{ route('admin.leads.show', $lead) }}" class="font-semibold text-fg hover:text-fg-brand" dir="auto">{{ $lead->name }}</a>
                            @if ($lead->company)
                                <p class="text-xs text-fg-secondary" dir="auto">{{ $lead->company }}</p>
                            @endif
                            <div class="flex items-center justify-between gap-2 text-xs text-fg-secondary">
                                <span class="font-semibold text-fg" dir="ltr">{{ $lead->formattedEstimatedValue() ?? '—' }}</span>
                                @if ($lead->follow_up_at)
                                    <span>{{ $lead->follow_up_at->translatedFormat('j M') }}</span>
                                @endif
                                <span class="flex size-7 items-center justify-center rounded-full bg-brand-subtle text-[11px] font-semibold text-fg-brand" title="{{ $lead->assignee?->name ?? __('Unassigned') }}">
                                    {{ $lead->assignee?->initials() ?? '—' }}<span class="sr-only">{{ $lead->assignee?->name ?? __('Unassigned') }}</span>
                                </span>
                            </div>
                            @if ($canMove)
                                @include('admin.leads._move-form', ['lead' => $lead, 'compact' => true])
                            @endif
                        </li>
                    @endforeach
                </ul>
                @if ($column['total'] > $column['leads']->count())
                    <a href="{{ route('admin.leads.index', ['status' => $value]) }}" class="px-1 text-xs font-semibold text-fg-brand hover:underline">{{ __('See all :count in the table', ['count' => $column['total']]) }}</a>
                @endif
            </section>
        @endforeach
    </div>

    <p class="flex items-start gap-2 text-sm text-fg-secondary">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" class="mt-0.5 shrink-0 text-fg-info"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
        {{ __('Drag cards to change status, or use the “Move” control on each card with the keyboard. Every move is checked on the server and logged as lead activity.') }}
    </p>
</x-layouts.admin>
