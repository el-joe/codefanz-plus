<x-layouts.admin :title="__('Categories & tags')" :crumb="__('Blog')">
    @if ($errors->any())
        <x-ui.alert tone="danger" :title="__('Could not delete')">{{ $errors->first() }}</x-ui.alert>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-3">
        <nav aria-label="{{ __('Blog sections') }}">
            <ul class="inline-flex gap-1 rounded-lg bg-muted p-1 text-sm font-semibold">
                <li><a href="{{ route('admin.posts.index') }}" class="inline-block rounded-md px-3 py-1.5 text-fg-secondary hover:text-fg">{{ __('Articles') }}</a></li>
                <li><a href="{{ route('admin.post-categories.index') }}" aria-current="page" class="inline-block rounded-md bg-surface px-3 py-1.5 text-fg shadow-sm">{{ __('Categories & tags') }}</a></li>
            </ul>
        </nav>
        <div class="flex gap-2">
            <x-ui.button :href="route('admin.post-categories.create')">{{ __('New category') }}</x-ui.button>
            <x-ui.button :href="route('admin.tags.create')" variant="secondary">{{ __('New tag') }}</x-ui.button>
        </div>
    </div>

    @foreach (['categories' => [__('Categories'), $categories, 'admin.post-categories.edit', __('No categories yet')], 'tags' => [__('Tags'), $tags, 'admin.tags.edit', __('No tags yet')]] as $key => [$heading, $rows, $editRoute, $empty])
        <x-ui.card :title="$heading" padding="p-0" class="overflow-x-auto [&>div]:px-6 [&>div]:pt-5">
            @if ($rows->isEmpty())
                <x-ui.empty-state :title="$empty" />
            @else
                <table class="w-full text-sm">
                    <thead class="bg-subtle text-xs text-fg-tertiary">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Name') }}</th>
                            <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Languages') }}</th>
                            <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Articles') }}</th>
                            @if ($key === 'categories')
                                <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Order') }}</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr class="border-t border-line-subtle">
                                <td class="px-4 py-3"><a href="{{ route($editRoute, $row) }}" class="font-semibold text-fg hover:text-fg-brand" dir="auto">{{ $row->translation()?->name ?? '#'.$row->id }}</a></td>
                                <td class="px-4 py-3">@include('admin._languages', ['model' => $row])</td>
                                <td class="px-4 py-3 text-fg-secondary">{{ $row->posts_count }}</td>
                                @if ($key === 'categories')
                                    <td class="px-4 py-3 text-fg-secondary">{{ $row->sort_order }}</td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-ui.card>
    @endforeach
</x-layouts.admin>
