<x-layouts.admin :title="__('Portfolio')" :crumb="__('Content & SEO')">
    @if ($errors->any())
        <x-ui.alert tone="danger" :title="__('Action not completed')">{{ $errors->first() }}</x-ui.alert>
    @endif

    <div class="flex flex-wrap items-center gap-3">
        <p class="flex-1 text-sm text-fg-secondary">{{ __('Published, public projects mark their technologies as “Verified in projects”. Client names stay hidden unless the client agreed to be named.') }}</p>
        @can('create', \App\Models\PortfolioCategory::class)
            <x-ui.button variant="secondary" :href="route('admin.portfolio-categories.create')">{{ __('New category') }}</x-ui.button>
        @endcan
        @can('create', \App\Models\PortfolioProject::class)
            <x-ui.button :href="route('admin.portfolio.create')">{{ __('New project') }}</x-ui.button>
        @endcan
    </div>

    <x-ui.card :title="__('Categories')">
        @if ($categories->isEmpty())
            <p class="text-sm text-fg-secondary">{{ __('No categories yet. Categories become the tabs on the public portfolio page.') }}</p>
        @else
            <ul class="flex flex-wrap gap-2">
                @foreach ($categories as $category)
                    <li>
                        <a href="{{ route('admin.portfolio-categories.edit', $category) }}" class="inline-flex items-center gap-2 rounded-full border border-line px-3 py-1.5 text-sm font-semibold text-fg hover:border-line-brand" dir="auto">
                            {{ $category->translation()?->name ?? '#'.$category->id }}
                            <span class="text-xs font-normal text-fg-tertiary">{{ $category->projects_count }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>

    <x-ui.card padding="p-0" class="overflow-x-auto">
        @if ($projects->isEmpty())
            <x-ui.empty-state :title="__('No projects yet')" :description="__('Add case studies in both languages.')" />
        @else
            <table class="w-full text-sm">
                <thead class="bg-subtle text-xs text-fg-tertiary">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Title') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Type') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Category') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Languages') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Status') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Order') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($projects as $project)
                        <tr class="border-t border-line-subtle">
                            <td class="px-4 py-3">
                                <a href="{{ route('admin.portfolio.edit', $project) }}" class="font-semibold text-fg hover:text-fg-brand" dir="auto">{{ $project->translation()?->title ?? __('Untitled') }}</a>
                                @if ($project->is_featured)
                                    <x-ui.badge tone="brand" :dot="false">{{ __('Featured') }}</x-ui.badge>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-fg-secondary">{{ $project->type->label() }}</td>
                            <td class="px-4 py-3 text-fg-secondary" dir="auto">{{ $project->category?->translation()?->name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="flex gap-1.5">
                                    @foreach (\App\Models\Language::active() as $code => $language)
                                        <x-ui.badge :tone="$project->translations->contains('locale', $code) ? 'success' : 'neutral'" :dot="false">{{ strtoupper($code) }}</x-ui.badge>
                                    @endforeach
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <x-ui.badge :tone="$project->status === \App\Enums\ContentStatus::Published ? 'success' : 'neutral'">{{ $project->status->label() }}</x-ui.badge>
                                @if ($project->visibility === \App\Enums\ProjectVisibility::Unlisted)
                                    <x-ui.badge tone="warning" :dot="false">{{ __('Unlisted') }}</x-ui.badge>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-fg-secondary">{{ $project->sort_order }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-ui.card>

    {{ $projects->links() }}
</x-layouts.admin>
