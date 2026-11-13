{{-- Social metrics: only what the platforms reported (latest snapshot per post). Manual posts and platforms without an
     API say so instead of showing a number; "—" means the platform does not report that metric. --}}
@php
    use App\Services\Social\SocialMetrics;

    $format = fn (?int $value): string => $value === null ? '—' : number_format($value);
    $chart = collect($dashboard['byPlatform'])->filter(fn ($row) => $row['impressions'] !== null);
    $timezone = auth()->user()->timezone ?: config('app.timezone');
@endphp

<x-layouts.admin :title="__('Social metrics')" :crumb="__('Social')">
    @include('admin.social._tabs')

    <div class="flex flex-wrap items-end justify-between gap-4">
        <form method="GET" data-auto-submit class="flex items-end gap-3">
            <x-ui.select name="platform" :label="__('Platform')" :value="$platform?->value ?? ''" :placeholder="__('All platforms')"
                         :options="collect(\App\Enums\SocialPlatform::cases())->mapWithKeys(fn ($item) => [$item->value => $item->label()])->all()" />
            <noscript><x-ui.button variant="secondary">{{ __('Filter') }}</x-ui.button></noscript>
        </form>
        <form method="POST" action="{{ route('admin.social.metrics.refresh') }}">
            @csrf
            <x-ui.button variant="secondary">{{ __('Refresh now') }}</x-ui.button>
        </form>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        @foreach (['impressions' => __('Impressions'), 'engagements' => __('Engagements'), 'clicks' => __('Clicks')] as $key => $label)
            <x-ui.card data-kpi="{{ $key }}">
                <p class="text-sm text-fg-secondary">{{ $label }}</p>
                <p class="mt-2 text-3xl font-bold text-fg">{{ $format($dashboard['totals'][$key]) }}</p>
                <p class="mt-2 text-xs text-fg-tertiary">{{ __('From :count post(s) with platform data', ['count' => $dashboard['reporting']]) }}</p>
            </x-ui.card>
        @endforeach
    </div>

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1fr)_360px]">
        <x-ui.card :title="__('Impressions by platform')">
            @if ($chart->isNotEmpty())
                <div class="h-64"><canvas data-analytics-chart="social-chart-data" role="img" aria-label="{{ __('Impressions by platform') }}"></canvas></div>
                <script type="application/json" id="social-chart-data">@json(['label' => __('Impressions'), 'labels' => $chart->pluck('label')->values(), 'series' => $chart->pluck('impressions')->values()], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE)</script>
            @else
                <x-ui.empty-state :title="__('No platform data yet')" :description="__('Numbers appear after posts are published through a connected account and the platform reports them.')" />
            @endif
        </x-ui.card>

        <x-ui.card :title="__('By platform')">
            @forelse ($dashboard['byPlatform'] as $row)
                <div class="mb-3 border-b border-line-subtle pb-3 text-sm last:border-0">
                    <p class="font-semibold text-fg">{{ $row['label'] }} <span class="text-xs font-normal text-fg-tertiary">· {{ __(':count post(s)', ['count' => $row['posts']]) }}</span></p>
                    <p class="text-xs text-fg-secondary">{{ __('Impressions') }} {{ $format($row['impressions']) }} · {{ __('Engagements') }} {{ $format($row['engagements']) }} · {{ __('Clicks') }} {{ $format($row['clicks']) }}</p>
                </div>
            @empty
                <p class="text-sm text-fg-tertiary">{{ __('No published posts yet.') }}</p>
            @endforelse
        </x-ui.card>
    </div>

    <x-ui.card padding="p-0" class="overflow-x-auto">
        @if ($dashboard['rows']->isEmpty())
            <x-ui.empty-state :title="__('No published posts yet.')" />
        @else
            <table class="w-full text-sm">
                <thead class="bg-subtle text-xs text-fg-tertiary">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Post') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Platform') }}</th>
                        <th scope="col" class="px-4 py-3 text-end font-semibold">{{ __('Impressions') }}</th>
                        <th scope="col" class="px-4 py-3 text-end font-semibold">{{ __('Engagements') }}</th>
                        <th scope="col" class="px-4 py-3 text-end font-semibold">{{ __('Clicks') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Data') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($dashboard['rows'] as $variant)
                        @php
                            $state = $metrics->stateOf($variant);
                            $snapshot = $variant->latestSnapshot;
                        @endphp
                        <tr class="border-t border-line-subtle" data-metrics-row="{{ $state }}">
                            <td class="px-4 py-3">
                                <a href="{{ route('admin.social.posts.show', $variant->post) }}" class="font-semibold text-fg hover:text-fg-brand" dir="auto">{{ $variant->post->title }}</a>
                                <p class="text-xs text-fg-tertiary">{{ ($variant->published_at ?? $variant->exported_at)?->timezone($timezone)->translatedFormat('j M Y') }}</p>
                            </td>
                            <td class="px-4 py-3 text-fg-secondary" dir="auto">{{ $variant->account->displayName() }}</td>
                            @if ($state === SocialMetrics::AVAILABLE)
                                <td class="px-4 py-3 text-end text-fg">{{ $format($snapshot->impressions) }}</td>
                                <td class="px-4 py-3 text-end text-fg">{{ $format($snapshot->engagements) }}</td>
                                <td class="px-4 py-3 text-end text-fg">{{ $format($snapshot->clicks) }}</td>
                            @else
                                <td colspan="3" class="px-4 py-3 text-center text-xs text-fg-tertiary">{{ SocialMetrics::stateLabel($state) }}</td>
                            @endif
                            <td class="px-4 py-3 text-xs text-fg-secondary">
                                @if ($state === SocialMetrics::AVAILABLE)
                                    {{ __('Captured :date', ['date' => $snapshot->captured_at->timezone($timezone)->translatedFormat('j M H:i')]) }}
                                @elseif ($state === SocialMetrics::ERROR)
                                    <span class="text-fg-danger" dir="auto">{{ $variant->metrics_error }}</span>
                                @else
                                    {{ SocialMetrics::stateLabel($state) }}
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-ui.card>

    <p class="text-xs text-fg-tertiary">{{ __('Engagements = reactions, comments and shares (Instagram: total interactions). Metrics are pulled every six hours for posts from the last :days days.', ['days' => config('codefanz.social.metrics_window_days')]) }}</p>
</x-layouts.admin>
