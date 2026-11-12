<x-layouts.admin :title="__('Industries')" :crumb="__('Content & SEO')">
    <div class="flex justify-end">
        @can('create', \App\Models\Industry::class)
            <x-ui.button :href="route('admin.industries.create')">{{ __('New industry') }}</x-ui.button>
        @endcan
    </div>

    <x-ui.card padding="p-0" class="overflow-x-auto">
        @if ($industries->isEmpty())
            <x-ui.empty-state :title="__('No industries yet')" :description="__('Add the industries you serve with their typical solutions.')" />
        @else
            <table class="w-full text-sm">
                <thead class="bg-subtle text-xs text-fg-tertiary">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Name') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Languages') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Status') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Order') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($industries as $industry)
                        <tr class="border-t border-line-subtle">
                            <td class="px-4 py-3"><a href="{{ route('admin.industries.edit', $industry) }}" class="font-semibold text-fg hover:text-fg-brand" dir="auto">{{ $industry->translation()?->name ?? __('Untitled') }}</a></td>
                            <td class="px-4 py-3">
                                <span class="flex gap-1.5">
                                    @foreach (\App\Models\Language::active() as $code => $language)
                                        <x-ui.badge :tone="$industry->translations->contains('locale', $code) ? 'success' : 'neutral'" :dot="false">{{ strtoupper($code) }}</x-ui.badge>
                                    @endforeach
                                </span>
                            </td>
                            <td class="px-4 py-3"><x-ui.badge :tone="$industry->status === \App\Enums\ContentStatus::Published ? 'success' : 'neutral'">{{ $industry->status->label() }}</x-ui.badge></td>
                            <td class="px-4 py-3 text-fg-secondary">{{ $industry->sort_order }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-ui.card>

    {{ $industries->links() }}
</x-layouts.admin>
