@php($progress = $project->progressPercent())
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
    @foreach ([
        __('Total value') => $project->formattedBudget() ?? '—',
        __('Milestones total') => $milestonesTotal,
        __('Start date') => $project->start_date?->translatedFormat('j M Y') ?? '—',
        __('Expected delivery') => $project->due_date?->translatedFormat('j M Y') ?? '—',
    ] as $label => $value)
        <x-ui.card padding="p-4">
            <p class="text-xs text-fg-tertiary">{{ $label }}</p>
            <p class="mt-1 text-xl font-semibold text-fg" dir="ltr">{{ $value }}</p>
        </x-ui.card>
    @endforeach
</div>

<div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
    <div class="flex min-w-0 flex-col gap-6">
        <x-ui.card :title="__('Progress')">
            <div class="flex items-center justify-between text-sm text-fg-secondary">
                <span>{{ __(':done of :total milestones approved or completed', ['done' => $project->milestones->filter(fn ($m) => $m->status->isDone())->count(), 'total' => $project->milestones->count()]) }}</span>
                <span class="font-semibold text-fg">{{ $progress }}%</span>
            </div>
            <div class="mt-2 h-2 overflow-hidden rounded-full bg-muted" role="progressbar" aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100"><div class="h-full rounded-full bg-brand" style="width: {{ $progress }}%"></div></div>
            @if (filled($project->description))
                <p class="mt-4 whitespace-pre-line text-sm text-fg" dir="auto">{{ $project->description }}</p>
            @endif
        </x-ui.card>

        <x-ui.card :title="__('Notes and client updates')">
            @if ($canManage)
                <form method="POST" action="{{ route('admin.projects.notes.store', $project) }}" class="mb-6 flex flex-col gap-3 rounded-lg border border-line p-4" data-note-form>
                    @csrf
                    @include('admin._textarea', ['name' => 'body', 'id' => 'note-body', 'label' => __('Write a note or update'), 'value' => null, 'rows' => 3, 'max' => 5000])
                    <fieldset class="flex flex-wrap gap-4 text-sm">
                        <legend class="sr-only">{{ __('Who can see this?') }}</legend>
                        <label class="flex items-center gap-2 text-fg"><input type="radio" name="visibility" value="internal" @checked(old('visibility', 'internal') === 'internal') class="accent-brand"> {{ __('Internal note (team only)') }}</label>
                        <label class="flex items-center gap-2 text-fg"><input type="radio" name="visibility" value="client" @checked(old('visibility') === 'client') class="accent-brand"> {{ __('Client update (shown in the portal)') }}</label>
                    </fieldset>
                    @error('visibility')<p class="text-sm text-fg-danger">{{ $message }}</p>@enderror
                    <x-ui.button size="sm" class="w-fit">{{ __('Add') }}</x-ui.button>
                </form>
            @endif

            @if ($notes->isEmpty())
                <p class="text-sm text-fg-secondary">{{ __('No notes or updates yet.') }}</p>
            @else
                <ul class="flex flex-col gap-3" data-project-notes>
                    @foreach ($notes as $note)
                        <li class="rounded-lg border p-4 {{ $note->visibility === \App\Enums\ClientVisibility::Internal ? 'border-line-danger/40 bg-danger-subtle/30' : 'border-line' }}" data-note-visibility="{{ $note->visibility->value }}">
                            <div class="mb-2 flex flex-wrap items-center gap-2 text-xs text-fg-tertiary">
                                <x-ui.badge :tone="$note->visibility->tone()">{{ $note->visibility === \App\Enums\ClientVisibility::Internal ? __('Never visible to client') : __('Shared with client') }}</x-ui.badge>
                                <span dir="auto">{{ $note->author?->name ?? __('Former user') }}</span> · <span>{{ $note->created_at->translatedFormat('j M Y, H:i') }}</span>
                            </div>
                            <p class="whitespace-pre-line text-sm text-fg" dir="auto">{{ $note->body }}</p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>
    </div>

    <div class="flex flex-col gap-6">
        <x-ui.card :title="__('Team')">
            <dl class="mb-3 text-sm">
                <dt class="text-fg-tertiary">{{ __('Project manager') }}</dt>
                <dd class="font-semibold text-fg" dir="auto">{{ $project->manager?->name ?? __('Unassigned') }}</dd>
            </dl>
            @if ($project->members->isEmpty())
                <p class="text-sm text-fg-secondary">{{ __('No team members yet.') }}</p>
            @else
                <ul class="flex flex-col gap-2 text-sm">
                    @foreach ($project->members as $member)
                        <li class="flex justify-between gap-2"><span class="text-fg" dir="auto">{{ $member->name }}</span><span class="text-fg-tertiary" dir="auto">{{ $member->pivot->role }}</span></li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>

        <x-ui.card :title="__('Client portal')">
            <ul class="flex flex-col gap-2 text-sm text-fg-secondary">
                <li>{{ $project->show_budget_to_client ? __('Client sees the project value and milestone amounts.') : __('Project value and amounts are hidden from the client.') }}</li>
                <li>{{ $project->show_team_to_client ? __('Client sees the project manager and team.') : __('The team is hidden from the client.') }}</li>
                <li>{{ __(':count client updates shared.', ['count' => $clientUpdatesCount]) }}</li>
            </ul>
        </x-ui.card>

        <x-ui.card :title="__('Details')">
            <dl class="flex flex-col divide-y divide-line-subtle text-sm">
                <div class="flex justify-between gap-4 py-2"><dt class="text-fg-tertiary">{{ __('Reference') }}</dt><dd class="font-mono font-semibold text-fg" dir="ltr">{{ $project->reference }}</dd></div>
                @if ($project->lead)
                    <div class="flex justify-between gap-4 py-2"><dt class="text-fg-tertiary">{{ __('Source lead') }}</dt><dd>
                        @can('view', $project->lead)
                            <a href="{{ route('admin.leads.show', $project->lead) }}" class="font-semibold text-fg-brand hover:underline" dir="ltr">{{ $project->lead->reference }}</a>
                        @else
                            <span dir="ltr">{{ $project->lead->reference }}</span>
                        @endcan
                    </dd></div>
                @endif
                <div class="flex justify-between gap-4 py-2"><dt class="text-fg-tertiary">{{ __('Created') }}</dt><dd class="text-fg">{{ $project->created_at->translatedFormat('j M Y') }}</dd></div>
            </dl>
        </x-ui.card>

        @can('delete', $project)
            <x-ui.card :title="__('Delete project')">
                <p class="mb-3 text-sm text-fg-secondary">{{ __('The project disappears from the admin and the client portal. An administrator can restore it from the database.') }}</p>
                <form method="POST" action="{{ route('admin.projects.destroy', $project) }}" data-confirm="{{ __('Delete :name?', ['name' => $project->name]) }}" data-confirm-label="{{ __('Delete') }}">
                    @csrf
                    @method('DELETE')
                    <x-ui.button variant="danger" size="sm">{{ __('Delete project') }}</x-ui.button>
                </form>
            </x-ui.card>
        @endcan
    </div>
</div>
