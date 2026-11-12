<x-layouts.admin :title="__('Navigation & menus')" :crumb="__('Content & SEO')">
    <p class="text-sm text-fg-secondary">{{ __('Menus link to site sections, translated content or external https pages. Each language shows its own labels and URLs; links to unpublished or untranslated content are hidden automatically. An empty menu shows the built-in default links.') }}</p>

    <x-ui.card padding="p-0" class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-subtle text-xs text-fg-tertiary">
                <tr>
                    <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Menu') }}</th>
                    <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Items') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($locations as $location)
                    <tr class="border-t border-line-subtle">
                        <td class="px-4 py-3"><a href="{{ route('admin.menus.show', $location->value) }}" class="font-semibold text-fg hover:text-fg-brand">{{ $location->label() }}</a></td>
                        <td class="px-4 py-3 text-fg-secondary">
                            @if ($counts[$location->value] ?? 0)
                                {{ $counts[$location->value] }}
                            @else
                                <x-ui.badge :dot="false">{{ __('Default links') }}</x-ui.badge>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-ui.card>
</x-layouts.admin>
