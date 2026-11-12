{{-- Board / Table / My leads / Follow-ups due, plus "Add lead" for leads.manage. --}}
@php
    $tabs = [
        ['label' => __('Board'), 'url' => route('admin.leads.board'), 'active' => request()->routeIs('admin.leads.board')],
        ['label' => __('Table'), 'url' => route('admin.leads.index'), 'active' => request()->routeIs('admin.leads.index') && ! request()->hasAny(['follow_up']) && request('assignee') !== 'me'],
        ['label' => __('My leads'), 'url' => route('admin.leads.index', ['assignee' => 'me']), 'active' => request()->routeIs('admin.leads.index') && request('assignee') === 'me'],
        ['label' => __('Follow-ups due'), 'url' => route('admin.leads.index', ['follow_up' => 'due']), 'active' => request()->routeIs('admin.leads.index') && request('follow_up') === 'due'],
    ];
@endphp

<div class="flex flex-wrap items-center gap-3">
    <nav aria-label="{{ __('Lead views') }}" class="flex flex-wrap gap-1 rounded-lg bg-subtle p-1">
        @foreach ($tabs as $tab)
            <a href="{{ $tab['url'] }}" @if ($tab['active']) aria-current="page" @endif
               class="rounded-md px-3 py-1.5 text-sm font-semibold {{ $tab['active'] ? 'bg-surface text-fg shadow-sm' : 'text-fg-secondary hover:text-fg' }}">{{ $tab['label'] }}</a>
        @endforeach
    </nav>
    <div class="flex-1"></div>
    @can('create', \App\Models\Lead::class)
        <x-ui.button :href="route('admin.leads.create')">{{ __('Add lead') }}</x-ui.button>
    @endcan
</div>
