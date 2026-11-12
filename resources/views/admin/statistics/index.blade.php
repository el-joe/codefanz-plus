<x-layouts.admin :title="__('Statistics')" :crumb="__('Content & SEO')">
    <div class="flex flex-wrap items-center gap-3">
        <p class="flex-1 text-sm text-fg-secondary">{{ __('Figures shown in the homepage statistics band. Only publish numbers you can back up.') }}</p>
        @can('create', \App\Models\Statistic::class)
            <x-ui.button :href="route('admin.statistics.create')">{{ __('New statistic') }}</x-ui.button>
        @endcan
    </div>

    <x-ui.card padding="p-0" class="overflow-x-auto">
        @if ($statistics->isEmpty())
            <x-ui.empty-state :title="__('No statistics yet')" :description="__('The statistics band stays hidden until you publish at least one figure.')" />
        @else
            <table class="w-full text-sm">
                <thead class="bg-subtle text-xs text-fg-tertiary">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Value') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Label') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Languages') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Status') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Order') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($statistics as $statistic)
                        @php($translation = $statistic->translation())
                        <tr class="border-t border-line-subtle">
                            <td class="px-4 py-3"><a href="{{ route('admin.statistics.edit', $statistic) }}" class="font-semibold text-fg hover:text-fg-brand" dir="ltr">{{ $statistic->formattedValue() }}{{ $translation?->suffix }}</a></td>
                            <td class="px-4 py-3 text-fg-secondary" dir="auto">{{ $translation?->label ?? '—' }}</td>
                            <td class="px-4 py-3">@include('admin._languages', ['model' => $statistic])</td>
                            <td class="px-4 py-3"><x-ui.badge :tone="$statistic->status === \App\Enums\ContentStatus::Published ? 'success' : 'neutral'">{{ $statistic->status->label() }}</x-ui.badge></td>
                            <td class="px-4 py-3 text-fg-secondary">{{ $statistic->sort_order }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-ui.card>
</x-layouts.admin>
