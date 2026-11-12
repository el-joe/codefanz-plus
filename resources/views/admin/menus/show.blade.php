<x-layouts.admin :title="$menu->location->label()" :crumb="__('Navigation & menus')">
    <div class="flex flex-wrap items-center gap-3">
        <p class="flex-1 text-sm text-fg-secondary">
            {{ $menu->location->allowsChildren() ? __('Up to two levels: top-level items can have sub-items shown as a dropdown.') : __('A flat list of links.') }}
        </p>
        <x-ui.button variant="secondary" :href="route('admin.menus.index')">{{ __('All menus') }}</x-ui.button>
        @can('create', \App\Models\MenuItem::class)
            <x-ui.button :href="route('admin.menus.items.create', $menu)">{{ __('Add item') }}</x-ui.button>
        @endcan
    </div>

    <x-ui.card padding="p-0" class="overflow-x-auto">
        @if ($topLevel->isEmpty())
            <x-ui.empty-state :title="__('This menu is empty')" :description="__('Visitors see the built-in default links until you add items.')" />
        @else
            <table class="w-full text-sm">
                <thead class="bg-subtle text-xs text-fg-tertiary">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Label') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Links to') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Languages') }}</th>
                        <th scope="col" class="px-4 py-3 text-end font-semibold">{{ __('Order') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($topLevel as $item)
                        @include('admin.menus._row', ['item' => $item, 'child' => false])
                        @foreach ($children->get($item->id, collect()) as $childItem)
                            @include('admin.menus._row', ['item' => $childItem, 'child' => true])
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-ui.card>
</x-layouts.admin>
