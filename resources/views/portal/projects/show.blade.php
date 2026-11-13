@php
    $progress = $project->progressPercent();
    $doneCount = $project->milestones->filter(fn ($milestone) => $milestone->status->isDone())->count();
@endphp

<x-layouts.portal :title="$project->name" :crumb="__('My Projects')">
    <x-ui.card>
        <div class="flex flex-wrap items-start gap-4">
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <x-ui.badge :tone="$project->status->tone()">{{ $project->status->label() }}</x-ui.badge>
                    <span class="font-mono text-xs text-fg-tertiary" dir="ltr">{{ $project->reference }}</span>
                </div>
                <h2 class="mt-2 text-2xl font-semibold text-fg" dir="auto">{{ $project->name }}</h2>
                <p class="mt-1 text-sm text-fg-secondary">
                    @if ($project->start_date){{ __('Started :date', ['date' => $project->start_date->translatedFormat('j M Y')]) }}@endif
                    @if ($project->start_date && $project->due_date) · @endif
                    @if ($project->due_date){{ __('Expected delivery :date', ['date' => $project->due_date->translatedFormat('j M Y')]) }}@endif
                </p>
            </div>
            @if ($conversationUlid)
                <x-ui.button variant="secondary" size="sm" :href="route('portal.conversations.show', $conversationUlid)">{{ __('Message team') }}</x-ui.button>
            @endif
        </div>
        <nav class="mt-5 flex flex-wrap gap-1 border-b border-line" aria-label="{{ __('Project sections') }}">
            @foreach (['overview' => __('Overview'), 'milestones' => __('Milestones'), 'documents' => __('Documents')] as $key => $label)
                <a href="{{ route('portal.projects.show', ['project' => $project, 'tab' => $key]) }}" data-tab="{{ $key }}" @if ($tab === $key) aria-current="page" @endif
                   class="-mb-px whitespace-nowrap border-b-2 px-3 py-2 text-sm font-semibold {{ $tab === $key ? 'border-brand text-fg-brand' : 'border-transparent text-fg-secondary hover:text-fg' }}">{{ $label }}</a>
            @endforeach
        </nav>
    </x-ui.card>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @if ($project->show_budget_to_client && $project->budget_minor !== null)
            <x-ui.card padding="p-4"><p class="text-xs text-fg-tertiary">{{ __('Total value') }}</p><p class="mt-1 text-xl font-semibold text-fg" dir="ltr">{{ $project->formattedBudget() }}</p></x-ui.card>
        @endif
        <x-ui.card padding="p-4"><p class="text-xs text-fg-tertiary">{{ __('Progress') }}</p><p class="mt-1 text-xl font-semibold text-fg">{{ $progress }}%</p></x-ui.card>
        <x-ui.card padding="p-4"><p class="text-xs text-fg-tertiary">{{ __('Milestones') }}</p><p class="mt-1 text-xl font-semibold text-fg">{{ __(':done of :total done', ['done' => $doneCount, 'total' => $project->milestones->count()]) }}</p></x-ui.card>
        <x-ui.card padding="p-4"><p class="text-xs text-fg-tertiary">{{ __('Delivery') }}</p><p class="mt-1 text-xl font-semibold text-fg">{{ $project->due_date?->translatedFormat('j M Y') ?? __('To be confirmed') }}</p></x-ui.card>
    </div>

    @include('portal.projects._tab-'.$tab)
</x-layouts.portal>
