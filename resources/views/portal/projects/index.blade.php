<x-layouts.portal :title="__('My Projects')" :crumb="__('My Projects')">
    @include('portal._tabs', ['label' => __('Filter projects'), 'tabs' => [
        [__('Active (:count)', ['count' => $counts['active']]), route('portal.projects.index'), $filter === 'active'],
        [__('Completed (:count)', ['count' => $counts['completed']]), route('portal.projects.index', ['status' => 'completed']), $filter === 'completed'],
        [__('All (:count)', ['count' => $counts['all']]), route('portal.projects.index', ['status' => 'all']), $filter === 'all'],
    ]])

    @if ($projects->isEmpty())
        <x-ui.card padding="p-6">
            @include('portal._empty-module', [
                'title' => __('No projects yet'),
                'description' => __('When CodeFanz starts your project you will follow its progress, milestones and deliverables here.'),
                'icon' => '<path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.93a2 2 0 0 1-1.66-.9l-.82-1.2A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13c0 1.1.9 2 2 2Z"/>',
            ])
        </x-ui.card>
    @else
        <div class="grid gap-4 md:grid-cols-2" data-projects>
            @foreach ($projects as $project)
                @php($progress = $project->progressPercent())
                @php($next = $project->nextMilestone())
                <a href="{{ route('portal.projects.show', $project) }}" class="flex flex-col gap-4 rounded-xl border border-line bg-surface p-5 hover:border-line-strong" data-project="{{ $project->ulid }}">
                    <div class="flex flex-wrap items-center gap-2">
                        <x-ui.badge :tone="$project->status->tone()">{{ $project->status->label() }}</x-ui.badge>
                        <span class="font-mono text-xs text-fg-tertiary" dir="ltr">{{ $project->reference }}</span>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-fg" dir="auto">{{ $project->name }}</h2>
                        <p class="text-sm text-fg-secondary">
                            {{ $project->due_date ? __('Expected delivery :date', ['date' => $project->due_date->translatedFormat('j M Y')]) : __('Delivery date to be confirmed') }}
                        </p>
                    </div>
                    <div>
                        <div class="mb-1 flex justify-between text-xs text-fg-secondary"><span>{{ __('Progress') }}</span><span class="font-semibold text-fg">{{ $progress }}%</span></div>
                        <div class="h-2 overflow-hidden rounded-full bg-muted" role="progressbar" aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ __('Progress') }}"><div class="h-full rounded-full bg-brand" style="width: {{ $progress }}%"></div></div>
                    </div>
                    <p class="text-sm text-fg-secondary">
                        @if ($next)
                            {{ __('Next milestone:') }} <span class="font-semibold text-fg" dir="auto">{{ $next->title }}</span>@if ($next->due_date) · {{ $next->due_date->translatedFormat('j M') }}@endif
                        @elseif ($project->milestones->isNotEmpty())
                            {{ __('All milestones are done.') }}
                        @else
                            {{ __('Milestones will appear here soon.') }}
                        @endif
                    </p>
                    @if ($project->show_budget_to_client && $project->budget_minor !== null)
                        <p class="text-sm text-fg-secondary">{{ __('Total value') }}: <span class="font-semibold text-fg" dir="ltr">{{ $project->formattedBudget() }}</span></p>
                    @endif
                </a>
            @endforeach
        </div>
    @endif
</x-layouts.portal>
