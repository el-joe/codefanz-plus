<x-layouts.admin :title="__('Technologies')" :crumb="__('Content & SEO')">
    @if ($errors->any())
        <x-ui.alert tone="danger" :title="__('Action not completed')">{{ $errors->first() }}</x-ui.alert>
    @endif

    <div class="flex flex-wrap items-center gap-3">
        <p class="flex-1 text-sm text-fg-secondary">{{ __('"Verified in projects" is calculated automatically from published portfolio projects. You can only choose Supported or Expertise.') }}</p>
        @can('create', \App\Models\TechnologyCategory::class)
            <x-ui.button variant="secondary" :href="route('admin.technology-categories.create')">{{ __('New category') }}</x-ui.button>
        @endcan
        @can('create', \App\Models\Technology::class)
            @if ($categories->isNotEmpty())
                <x-ui.button :href="route('admin.technologies.create')">{{ __('New technology') }}</x-ui.button>
            @endif
        @endcan
    </div>

    @forelse ($categories as $category)
        <x-ui.card padding="p-0" class="overflow-x-auto">
            <div class="flex items-center gap-3 border-b border-line-subtle px-4 py-3">
                <h2 class="flex-1 text-base font-semibold text-fg" dir="auto">{{ $category->translation()?->name ?? $category->key }}</h2>
                <a href="{{ route('admin.technology-categories.edit', $category) }}" class="text-sm font-semibold text-fg-brand hover:underline">{{ __('Edit category') }}</a>
            </div>
            @if ($category->technologies->isEmpty())
                <x-ui.empty-state :title="__('No technologies in this category')" />
            @else
                <table class="w-full text-sm">
                    <thead class="bg-subtle text-xs text-fg-tertiary">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Name') }}</th>
                            <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Public level') }}</th>
                            <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Visibility') }}</th>
                            <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Order') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($category->technologies as $technology)
                            @php($level = $technology->proficiency())
                            @php($tone = ['verified_in_projects' => 'accent', 'expertise' => 'brand'][$level->value] ?? 'neutral')
                            <tr class="border-t border-line-subtle">
                                <td class="px-4 py-3"><a href="{{ route('admin.technologies.edit', $technology) }}" class="font-semibold text-fg hover:text-fg-brand" dir="auto">{{ $technology->translation()?->name ?? $technology->key }}</a></td>
                                <td class="px-4 py-3"><x-ui.badge :tone="$tone">{{ $level->label() }}</x-ui.badge></td>
                                <td class="px-4 py-3 text-fg-secondary">{{ $technology->is_visible ? __('Visible') : __('Hidden') }}</td>
                                <td class="px-4 py-3 text-fg-secondary">{{ $technology->sort_order }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-ui.card>
    @empty
        <x-ui.card>
            <x-ui.empty-state :title="__('No technology categories yet')" :description="__('Create a category such as Programming languages or Frameworks, then add technologies to it.')" />
        </x-ui.card>
    @endforelse
</x-layouts.admin>
