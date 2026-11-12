@php
    $canManage = auth()->user()->can('update', $project);
    $tabs = [
        'overview' => __('Overview'),
        'milestones' => __('Milestones (:count)', ['count' => $project->milestones->count()]),
        'documents' => __('Documents'),
        'conversation' => __('Conversation'),
        'tickets' => __('Tickets'),
    ];
@endphp

<x-layouts.admin :title="$project->reference" :crumb="__('Projects')">
    <x-ui.card>
        <div class="flex flex-wrap items-start gap-4">
            <div class="min-w-0 flex-1">
                <h2 class="text-2xl font-semibold text-fg" dir="auto">{{ $project->name }}</h2>
                <p class="mt-1 flex flex-wrap items-center gap-2 text-sm text-fg-secondary">
                    @can('view', $project->customer)
                        <a href="{{ route('admin.customers.show', $project->customer) }}" class="font-semibold text-fg-brand hover:underline" dir="auto">{{ $project->customer->company_name }}</a>
                    @else
                        <span dir="auto">{{ $project->customer->company_name }}</span>
                    @endcan
                    <x-ui.badge :tone="$project->status->tone()">{{ $project->status->label() }}</x-ui.badge>
                    <x-ui.badge tone="neutral" :dot="false">{{ $project->type->label() }}</x-ui.badge>
                </p>
            </div>
            <a href="{{ route('admin.projects.index') }}" class="text-sm font-semibold text-fg-brand hover:underline">{{ __('Back to projects') }}</a>
            @if ($canManage)
                <x-ui.button variant="secondary" size="sm" :href="route('admin.projects.edit', $project)">{{ __('Edit') }}</x-ui.button>
            @endif
        </div>

        <nav class="mt-5 flex flex-wrap gap-1 border-b border-line" aria-label="{{ __('Project sections') }}">
            @foreach ($tabs as $key => $label)
                <a href="{{ route('admin.projects.show', ['project' => $project, 'tab' => $key]) }}" data-tab="{{ $key }}" @if ($tab === $key) aria-current="page" @endif
                   class="-mb-px border-b-2 px-3 py-2 text-sm font-semibold {{ $tab === $key ? 'border-brand text-fg-brand' : 'border-transparent text-fg-secondary hover:text-fg' }}">{{ $label }}</a>
            @endforeach
        </nav>
    </x-ui.card>

    @include('admin.projects._tab-'.$tab)
</x-layouts.admin>
