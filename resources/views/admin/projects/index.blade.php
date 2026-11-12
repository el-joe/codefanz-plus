<x-layouts.admin :title="__('Projects')" :crumb="__('Delivery')">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <form method="GET" action="{{ route('admin.projects.index') }}" class="flex flex-wrap items-end gap-3" role="search">
            <div class="w-full sm:w-72">
                <x-ui.input name="q" type="search" :label="__('Search')" :value="$filters['q'] ?? null" :placeholder="__('Project, customer or reference')" />
            </div>
            <div class="w-full sm:w-44">
                <x-ui.select name="status" :label="__('Status')" :value="$filters['status'] ?? null" :placeholder="__('All statuses')" :options="\App\Enums\ProjectStatus::options()" />
            </div>
            <div class="w-full sm:w-44">
                <x-ui.select name="manager" :label="__('Project manager')" :value="$filters['manager'] ?? null" :placeholder="__('Anyone')" :options="['me' => __('Me')]" />
            </div>
            <x-ui.button variant="secondary">{{ __('Filter') }}</x-ui.button>
        </form>
        @can('create', \App\Models\Project::class)
            <x-ui.button :href="route('admin.projects.create')">{{ __('New project') }}</x-ui.button>
        @endcan
    </div>

    <x-ui.card padding="p-0" class="overflow-x-auto">
        @if ($projects->isEmpty())
            <x-ui.empty-state :title="__('No projects yet')" :description="__('Start a project from a customer page or from a won lead that became a customer.')" />
        @else
            <table class="w-full text-sm">
                <thead class="bg-subtle text-xs text-fg-tertiary">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Project') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Customer') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Project manager') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Progress') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Due date') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($projects as $project)
                        <tr class="border-t border-line-subtle">
                            <td class="px-4 py-3">
                                <a href="{{ route('admin.projects.show', $project) }}" class="font-semibold text-fg hover:text-fg-brand" dir="auto">{{ $project->name }}</a>
                                <p class="text-xs text-fg-tertiary"><span dir="ltr">{{ $project->reference }}</span> · {{ $project->type->label() }}</p>
                            </td>
                            <td class="px-4 py-3 text-fg-secondary" dir="auto">{{ $project->customer?->company_name ?? '—' }}</td>
                            <td class="px-4 py-3 text-fg-secondary" dir="auto">{{ $project->manager?->name ?? __('Unassigned') }}</td>
                            <td class="px-4 py-3">
                                @php($progress = $project->progressPercent())
                                <div class="flex items-center gap-2">
                                    <div class="h-1.5 w-24 overflow-hidden rounded-full bg-muted" role="progressbar" aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100"><div class="h-full rounded-full bg-brand" style="width: {{ $progress }}%"></div></div>
                                    <span class="text-xs text-fg-secondary">{{ $progress }}%</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-fg-secondary">{{ $project->due_date?->translatedFormat('j M Y') ?? '—' }}</td>
                            <td class="px-4 py-3"><x-ui.badge :tone="$project->status->tone()">{{ $project->status->label() }}</x-ui.badge></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-ui.card>

    {{ $projects->links() }}
</x-layouts.admin>
