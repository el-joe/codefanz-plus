<x-layouts.admin :title="__('Software products')" :crumb="__('Content & SEO')">
    <div class="flex flex-wrap items-center gap-3">
        <p class="flex-1 text-sm text-fg-secondary">{{ __('Products list licensing models only. Prices are never shown: every quote is preliminary until scope is confirmed.') }}</p>
        @can('create', \App\Models\Product::class)
            <x-ui.button :href="route('admin.products.create')">{{ __('New product') }}</x-ui.button>
        @endcan
    </div>

    <x-ui.card padding="p-0" class="overflow-x-auto">
        @if ($products->isEmpty())
            <x-ui.empty-state :title="__('No products yet')" :description="__('Add ready-made software products in both languages.')" />
        @else
            <table class="w-full text-sm">
                <thead class="bg-subtle text-xs text-fg-tertiary">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Name') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Availability') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Latest version') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Languages') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Status') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Order') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($products as $product)
                        <tr class="border-t border-line-subtle">
                            <td class="px-4 py-3"><a href="{{ route('admin.products.edit', $product) }}" class="font-semibold text-fg hover:text-fg-brand" dir="auto">{{ $product->translation()?->name ?? __('Untitled') }}</a></td>
                            <td class="px-4 py-3"><x-ui.badge :tone="$product->availability->tone()">{{ $product->availability->label() }}</x-ui.badge></td>
                            <td class="px-4 py-3 font-mono text-fg-secondary" dir="ltr">{{ $product->latestVersion() ? 'v'.$product->latestVersion()->version : '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="flex gap-1.5">
                                    @foreach (\App\Models\Language::active() as $code => $language)
                                        <x-ui.badge :tone="$product->translations->contains('locale', $code) ? 'success' : 'neutral'" :dot="false">{{ strtoupper($code) }}</x-ui.badge>
                                    @endforeach
                                </span>
                            </td>
                            <td class="px-4 py-3"><x-ui.badge :tone="$product->status === \App\Enums\ContentStatus::Published ? 'success' : 'neutral'">{{ $product->status->label() }}</x-ui.badge></td>
                            <td class="px-4 py-3 text-fg-secondary">{{ $product->sort_order }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-ui.card>

    {{ $products->links() }}
</x-layouts.admin>
