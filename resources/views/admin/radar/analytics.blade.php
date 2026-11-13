@php
    $t = $summary['thresholds'];
    $statusRows = collect($summary['by_status'])->mapWithKeys(fn ($count, $status) => [\App\Enums\RadarProjectStatus::from($status)->label() => $count])->all();
    $bandRows = [
        __('Immediate (≥:score)', ['score' => $t['immediate']]) => $summary['bands']['immediate'],
        __('Normal (:from–:to)', ['from' => $t['normal'], 'to' => $t['immediate'] - 1]) => $summary['bands']['normal'],
        __('Digest (:from–:to)', ['from' => $t['digest'], 'to' => $t['normal'] - 1]) => $summary['bands']['digest'],
        __('Saved only (<:score)', ['score' => $t['digest']]) => $summary['bands']['saved'],
    ];
@endphp

<x-layouts.admin :title="__('Radar analytics')" :crumb="__('Freelance Radar')">
    @include('admin.radar._nav')

    <form method="GET" class="flex items-end gap-3" data-auto-submit>
        <div class="w-44"><x-ui.select name="days" :label="__('Period')" :options="[7 => __('Last 7 days'), 30 => __('Last 30 days'), 90 => __('Last 90 days')]" :value="$days" /></div>
        <noscript><x-ui.button variant="secondary" size="sm">{{ __('Apply') }}</x-ui.button></noscript>
    </form>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([[__('New projects'), $summary['total']], [__('Applied on platform'), $summary['applied']], [__('Converted to leads'), $summary['converted']], [__('Won'), $summary['won'].' · '.__(':count via CRM', ['count' => $summary['converted_won']])]] as [$label, $value])
            <x-ui.card>
                <p class="text-sm text-fg-secondary">{{ $label }}</p>
                <p class="mt-3 text-3xl font-semibold text-fg">{{ $value }}</p>
            </x-ui.card>
        @endforeach
    </div>

    <x-ui.card :title="__('New projects per day')">
        @if ($summary['total'] > 0)
            <div class="h-64"><canvas data-analytics-chart="radar-daily-chart" role="img" aria-label="{{ __('New radar projects per day, last :days days', ['days' => $days]) }}"></canvas></div>
            <script type="application/json" id="radar-daily-chart">@json(['label' => __('Projects'), 'labels' => $summary['chart']['labels'], 'series' => $summary['chart']['series']], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE)</script>
            <details class="mt-4 text-sm">
                <summary class="cursor-pointer font-semibold text-fg-brand">{{ __('Show as table') }}</summary>
                <table class="mt-2 w-full text-sm">
                    <thead class="text-xs text-fg-tertiary"><tr><th scope="col" class="py-1 text-start">{{ __('Day') }}</th><th scope="col" class="py-1 text-end">{{ __('Projects') }}</th></tr></thead>
                    <tbody>
                        @foreach ($summary['chart']['labels'] as $index => $label)
                            <tr class="border-t border-line-subtle"><td class="py-1">{{ $label }}</td><td class="py-1 text-end">{{ $summary['chart']['series'][$index] }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </details>
        @else
            <x-ui.empty-state :title="__('No projects in this period')" :description="__('Numbers appear once a connector collects projects.')" />
        @endif
    </x-ui.card>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <x-ui.card :title="__('By platform')">@include('admin.radar._bars', ['rows' => $summary['by_platform']])</x-ui.card>
        <x-ui.card :title="__('By score band')">@include('admin.radar._bars', ['rows' => $bandRows])</x-ui.card>
        <x-ui.card :title="__('By status')">@include('admin.radar._bars', ['rows' => $statusRows])</x-ui.card>
        <x-ui.card :title="__('Most requested skills')">@include('admin.radar._bars', ['rows' => $summary['top_skills']])</x-ui.card>
    </div>
</x-layouts.admin>
