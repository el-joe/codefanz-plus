{{-- A13 · Analytics — GA4 & Search Console. Cached snapshots and CRM lead counts only; nothing is estimated. --}}
@php
    $number = fn (float $value, string $suffix = ''): string => ($suffix === '%' || floor($value) != $value ? number_format($value, 1) : number_format($value)).$suffix;
    $change = function (array $kpi) use ($days): string {
        if ($kpi['change'] === null) {
            return __('No comparison yet');
        }

        return ($kpi['change'] > 0 ? '+' : '').$kpi['change'].'% '.__('vs previous :days days', ['days' => $days]);
    };
    $changeTone = fn (array $kpi): string => $kpi['change'] === null || $kpi['change'] == 0 ? 'neutral' : (($kpi['change'] > 0) !== $kpi['lowerIsBetter'] ? 'success' : 'danger');
    $bars = function (array $rows): array {
        $max = max(1, ...array_map(fn ($row) => $row['value'], $rows ?: [['value' => 1]]));

        return array_map(fn ($row) => [...$row, 'percent' => (int) round($row['value'] / $max * 100)], $rows);
    };
@endphp

<x-layouts.admin :title="__('Analytics')" :crumb="__('Content & SEO')">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <nav aria-label="{{ __('Date range') }}" class="flex gap-1 rounded-lg bg-subtle p-1">
            @foreach (\App\Services\Analytics\AnalyticsDashboard::RANGES as $range)
                <a href="{{ route('admin.analytics.index', ['days' => $range]) }}" @if ($range === $days) aria-current="true" @endif
                   class="rounded-md px-3 py-1.5 text-sm font-semibold {{ $range === $days ? 'bg-surface text-fg shadow-xs ring-1 ring-line' : 'text-fg-secondary hover:bg-surface' }}">{{ __('Last :days days vs previous', ['days' => $range]) }}</a>
            @endforeach
        </nav>
        <form method="POST" action="{{ route('admin.analytics.sync') }}">
            @csrf
            <x-ui.button variant="secondary" :disabled="$ga4['state'] === 'not_configured' && $gsc['state'] === 'not_configured'">{{ __('Refresh now') }}</x-ui.button>
        </form>
    </div>

    @if ($serviceAccountEmail)
        <p class="text-xs text-fg-tertiary">{{ __('Service account:') }} <span class="font-mono" dir="ltr">{{ $serviceAccountEmail }}</span></p>
    @endif

    {{-- Lead submissions come from the CRM itself and are always available. --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([...$ga4['kpis'], 'leads' => $leads['kpi']] as $key => $kpi)
            @php($available = $key === 'leads' || $ga4['hasData'])
            <x-ui.card data-kpi="{{ $key }}">
                <p class="text-sm text-fg-secondary">{{ $kpi['label'] }}@if ($key === 'leads') <span class="text-xs text-fg-tertiary">· {{ __('CRM') }}</span>@endif</p>
                <p class="mt-2 text-3xl font-bold text-fg">{{ $available ? $number($kpi['value']) : '—' }}</p>
                <p class="mt-2">
                    @if ($available)
                        <x-ui.badge :tone="$changeTone($kpi)" :dot="false">{{ $change($kpi) }}</x-ui.badge>
                    @else
                        <span class="text-xs text-fg-tertiary">{{ __('No GA4 data') }}</span>
                    @endif
                </p>
            </x-ui.card>
        @endforeach
    </div>

    <div data-tabs class="flex flex-col gap-6">
        <div role="tablist" data-tablist hidden aria-label="{{ __('Analytics sources') }}" class="flex w-fit gap-1 rounded-lg bg-subtle p-1">
            <button type="button" role="tab" data-tab id="tab-ga4" aria-controls="panel-ga4" aria-selected="true" class="rounded-md px-3 py-1.5 text-sm font-semibold text-fg-secondary aria-selected:bg-surface aria-selected:text-fg aria-selected:shadow-xs">{{ __('Google Analytics 4') }}</button>
            <button type="button" role="tab" data-tab id="tab-gsc" aria-controls="panel-gsc" aria-selected="false" class="rounded-md px-3 py-1.5 text-sm font-semibold text-fg-secondary aria-selected:bg-surface aria-selected:text-fg aria-selected:shadow-xs">{{ __('Search Console') }}</button>
        </div>

        <section id="panel-ga4" role="tabpanel" aria-labelledby="tab-ga4" class="flex flex-col gap-4" data-source="ga4">
            <div class="flex flex-wrap items-center gap-3">
                <h2 class="text-lg font-semibold text-fg">{{ __('Google Analytics 4') }}</h2>
                @include('admin.analytics._state', ['source' => $ga4, 'name' => 'GA4'])
            </div>

            <div class="grid gap-4 xl:grid-cols-[1fr_360px]">
                <x-ui.card :title="__('Sessions per day')">
                    @if ($ga4['chart']['series'] !== [])
                        <div class="h-64"><canvas data-analytics-chart="sessions-chart-data" role="img" aria-label="{{ __('Sessions per day, last :days days', ['days' => $days]) }}"></canvas></div>
                        <script type="application/json" id="sessions-chart-data">@json(['label' => __('Sessions'), 'labels' => $ga4['chart']['labels'], 'series' => $ga4['chart']['series']], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE)</script>
                        <details class="mt-3 text-sm">
                            <summary class="cursor-pointer text-fg-secondary">{{ __('Show as table') }}</summary>
                            <table class="mt-2 w-full text-xs">
                                <thead><tr><th scope="col" class="py-1 text-start">{{ __('Day') }}</th><th scope="col" class="py-1 text-end">{{ __('Sessions') }}</th></tr></thead>
                                <tbody>
                                    @foreach ($ga4['chart']['labels'] as $index => $label)
                                        <tr class="border-t border-line-subtle"><td class="py-1">{{ $label }}</td><td class="py-1 text-end">{{ number_format($ga4['chart']['series'][$index]) }}</td></tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </details>
                    @else
                        <x-ui.empty-state :title="__('No GA4 data yet')" />
                    @endif
                </x-ui.card>

                <x-ui.card :title="__('Traffic sources')">
                    @forelse ($bars($ga4['channels']) as $row)
                        <div class="mb-3">
                            <p class="flex justify-between text-sm text-fg"><span>{{ $row['label'] }}</span><span class="text-fg-secondary">{{ number_format($row['value']) }}</span></p>
                            <div class="mt-1 h-1.5 rounded-full bg-muted"><div class="h-full rounded-full bg-brand" style="width: {{ $row['percent'] }}%"></div></div>
                        </div>
                    @empty
                        <p class="text-sm text-fg-tertiary">{{ __('No data yet') }}</p>
                    @endforelse
                </x-ui.card>
            </div>

            <div class="grid gap-4 xl:grid-cols-2">
                <x-ui.card :title="__('Conversion events')">
                    <p class="mb-3 text-xs text-fg-tertiary">{{ __('Lead submissions recorded in the CRM, last :days days.', ['days' => $days]) }}</p>
                    @foreach ($bars($leads['bySource']) as $row)
                        <div class="mb-3">
                            <p class="flex justify-between text-sm text-fg"><span>{{ $row['label'] }}</span><span class="text-fg-secondary">{{ number_format($row['value']) }}</span></p>
                            <div class="mt-1 h-1.5 rounded-full bg-muted"><div class="h-full rounded-full bg-fg-success" style="width: {{ $row['percent'] }}%"></div></div>
                        </div>
                    @endforeach
                    @if ($ga4['events'] !== [])
                        <p class="mt-4 text-sm font-semibold text-fg">{{ __('GA4 events (last 28 days)') }}</p>
                        <ul class="mt-2 flex flex-col gap-1 text-sm">
                            @foreach ($ga4['events'] as $event)
                                <li class="flex justify-between"><span class="font-mono text-xs" dir="ltr">{{ $event['label'] }}</span><span class="text-fg-secondary">{{ number_format($event['value']) }}</span></li>
                            @endforeach
                        </ul>
                    @endif
                </x-ui.card>

                <x-ui.card :title="__('Top landing pages (last 28 days)')">
                    @forelse ($ga4['landingPages'] as $row)
                        <p class="flex justify-between gap-3 border-t border-line-subtle py-1.5 text-sm first:border-t-0"><span class="truncate font-mono text-xs" dir="ltr">{{ rawurldecode($row['label']) }}</span><span class="text-fg-secondary">{{ number_format($row['value']) }}</span></p>
                    @empty
                        <p class="text-sm text-fg-tertiary">{{ __('No data yet') }}</p>
                    @endforelse
                </x-ui.card>
            </div>
        </section>

        <section id="panel-gsc" role="tabpanel" aria-labelledby="tab-gsc" class="flex flex-col gap-4" data-source="gsc">
            <div class="flex flex-wrap items-center gap-3">
                <h2 class="text-lg font-semibold text-fg">{{ __('Search Console') }}</h2>
                @include('admin.analytics._state', ['source' => $gsc, 'name' => 'Search Console'])
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($gsc['kpis'] as $key => $kpi)
                    <x-ui.card data-kpi="gsc-{{ $key }}">
                        <p class="text-sm text-fg-secondary">{{ $kpi['label'] }}</p>
                        <p class="mt-2 text-3xl font-bold text-fg">{{ $gsc['hasData'] ? $number($kpi['value'], $kpi['suffix']) : '—' }}</p>
                        @if ($gsc['hasData'])
                            <p class="mt-2"><x-ui.badge :tone="$changeTone($kpi)" :dot="false">{{ $change($kpi) }}</x-ui.badge></p>
                        @endif
                    </x-ui.card>
                @endforeach
            </div>

            <div class="grid gap-4 xl:grid-cols-2">
                @foreach (['queries' => __('Top queries'), 'pages' => __('Top pages'), 'countries' => __('Countries'), 'devices' => __('Devices')] as $report => $heading)
                    <x-ui.card :title="$heading.' · '.__('clicks, last 28 days')">
                        @forelse ($gsc[$report] as $row)
                            <p class="flex justify-between gap-3 border-t border-line-subtle py-1.5 text-sm first:border-t-0"><span class="truncate" dir="auto">{{ $report === 'pages' ? rawurldecode($row['label']) : $row['label'] }}</span><span class="text-fg-secondary">{{ number_format($row['value']) }}</span></p>
                        @empty
                            <p class="text-sm text-fg-tertiary">{{ __('No data yet') }}</p>
                        @endforelse
                    </x-ui.card>
                @endforeach
            </div>
        </section>
    </div>
</x-layouts.admin>
