<div class="grid gap-6 xl:grid-cols-[1fr_360px]">
    <div class="flex min-w-0 flex-col gap-6">
        <x-ui.card :title="__('Progress')">
            <p class="mb-2 flex justify-between text-sm text-fg-secondary">
                <span>{{ __(':done of :total milestones approved or completed', ['done' => $doneCount, 'total' => $project->milestones->count()]) }}</span>
                <span class="font-semibold text-fg">{{ $progress }}%</span>
            </p>
            <div class="h-2 overflow-hidden rounded-full bg-muted" role="progressbar" aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ __('Progress') }}"><div class="h-full rounded-full bg-brand" style="width: {{ $progress }}%"></div></div>
            @if ($project->milestones->isNotEmpty())
                <div class="mt-3 flex gap-1.5" aria-hidden="true">
                    @foreach ($project->milestones as $milestone)
                        <span class="h-1.5 flex-1 rounded-full {{ $milestone->status->isDone() ? 'bg-success' : ($milestone->status === \App\Enums\MilestoneStatus::Pending ? 'bg-muted' : 'bg-brand') }}"></span>
                    @endforeach
                </div>
            @endif
            @if (filled($project->description))
                <p class="mt-4 whitespace-pre-line text-sm text-fg" dir="auto">{{ $project->description }}</p>
            @endif
        </x-ui.card>

        <x-ui.card :title="__('Updates from your team')">
            @if ($updates->isEmpty())
                <p class="text-sm text-fg-secondary">{{ __('Your project manager will post updates here.') }}</p>
            @else
                <ul class="flex flex-col gap-4" data-client-updates>
                    @foreach ($updates as $update)
                        <li class="border-b border-line-subtle pb-4 last:border-0 last:pb-0">
                            <p class="mb-1 text-xs text-fg-tertiary"><span dir="auto">{{ $update->author?->name ?? __('CodeFanz team') }}</span> · {{ $update->created_at->translatedFormat('j M Y') }}</p>
                            <p class="whitespace-pre-line text-sm text-fg" dir="auto">{{ $update->body }}</p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>
    </div>

    <aside class="flex flex-col gap-6">
        @if ($project->show_team_to_client && ($project->manager || $project->members->isNotEmpty()))
            <x-ui.card :title="__('Your team')">
                <ul class="flex flex-col gap-3 text-sm" data-project-team>
                    @if ($project->manager)
                        <li class="flex items-center gap-3"><span class="flex size-9 items-center justify-center rounded-full bg-brand-subtle text-xs font-semibold text-fg-brand" aria-hidden="true">{{ $project->manager->initials() }}</span><span><span class="block font-semibold text-fg" dir="auto">{{ $project->manager->name }}</span><span class="text-xs text-fg-tertiary">{{ __('Project manager') }}</span></span></li>
                    @endif
                    @foreach ($project->members->reject(fn ($member) => $member->id === $project->project_manager_id) as $member)
                        <li class="flex items-center gap-3"><span class="flex size-9 items-center justify-center rounded-full bg-brand-subtle text-xs font-semibold text-fg-brand" aria-hidden="true">{{ $member->initials() }}</span><span><span class="block font-semibold text-fg" dir="auto">{{ $member->name }}</span>@if ($member->pivot->role)<span class="text-xs text-fg-tertiary" dir="auto">{{ $member->pivot->role }}</span>@endif</span></li>
                    @endforeach
                </ul>
            </x-ui.card>
        @endif

        <x-ui.card :title="__('Latest documents')">
            <x-slot:action><a href="{{ route('portal.projects.show', ['project' => $project, 'tab' => 'documents']) }}" class="text-sm font-semibold text-fg-brand hover:underline">{{ __('All documents') }}</a></x-slot:action>
            @if ($recentDocuments->isEmpty())
                <p class="text-sm text-fg-secondary">{{ __('No documents shared yet.') }}</p>
            @else
                <ul class="flex flex-col gap-2 text-sm">
                    @foreach ($recentDocuments as $document)
                        <li class="flex items-center gap-2"><a href="{{ route('portal.documents.download', $document) }}" class="min-w-0 flex-1 truncate font-semibold text-fg hover:text-fg-brand" dir="auto">{{ $document->title }}</a><span class="text-xs text-fg-tertiary">v{{ $document->version }} · {{ $document->humanSize() }}</span></li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>
    </aside>
</div>
