<x-layouts.admin :title="__('Services')" :crumb="__('Content & SEO')">
    <div class="flex justify-end">
        @can('create', \App\Models\Service::class)
            <x-ui.button :href="route('admin.services.create')">{{ __('New service') }}</x-ui.button>
        @endcan
    </div>

    <x-ui.card padding="p-0" class="overflow-x-auto">
        @if ($services->isEmpty())
            <x-ui.empty-state :title="__('No services yet')" :description="__('Add core and specialist services in both languages.')" />
        @else
            <table class="w-full text-sm">
                <thead class="bg-subtle text-xs text-fg-tertiary">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Title') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Group') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Languages') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Status') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Order') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($services as $service)
                        <tr class="border-t border-line-subtle">
                            <td class="px-4 py-3">
                                <a href="{{ route('admin.services.edit', $service) }}" class="font-semibold text-fg hover:text-fg-brand" dir="auto">{{ $service->translation()?->title ?? __('Untitled') }}</a>
                                @if ($service->parent)
                                    <p class="text-xs text-fg-tertiary">{{ __('Under :parent', ['parent' => $service->parent->translation()?->title ?? '#'.$service->parent_id]) }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-fg-secondary">{{ $service->tier->label() }}</td>
                            <td class="px-4 py-3">
                                <span class="flex gap-1.5">
                                    @foreach (\App\Models\Language::active() as $code => $language)
                                        <x-ui.badge :tone="$service->translations->contains('locale', $code) ? 'success' : 'neutral'" :dot="false">{{ strtoupper($code) }}</x-ui.badge>
                                    @endforeach
                                </span>
                            </td>
                            <td class="px-4 py-3"><x-ui.badge :tone="$service->status === \App\Enums\ContentStatus::Published ? 'success' : 'neutral'">{{ $service->status->label() }}</x-ui.badge></td>
                            <td class="px-4 py-3 text-fg-secondary">{{ $service->sort_order }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-ui.card>

    {{ $services->links() }}
</x-layouts.admin>
