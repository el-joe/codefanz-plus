@php
    $tileItems = [
        ['label' => __('New today'), 'value' => $tiles['new_today']],
        ['label' => __('Score ≥ :score (7 days)', ['score' => $thresholds['immediate']]), 'value' => $tiles['top_matches']],
        ['label' => __('Applied this month'), 'value' => $tiles['applied_month']],
        ['label' => __('Won this month'), 'value' => $tiles['won_month']],
    ];
    $featured = $projects->first();
@endphp

<x-layouts.admin :title="__('Freelance Projects Radar')" :crumb="__('AI & Automation')">
    @include('admin.radar._nav')

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($tileItems as $tile)
            <x-ui.card>
                <p class="text-sm text-fg-secondary">{{ $tile['label'] }}</p>
                <p class="mt-3 text-3xl font-semibold text-fg">{{ number_format($tile['value']) }}</p>
            </x-ui.card>
        @endforeach
    </div>

    @include('admin.radar._connectors')

    <div class="flex flex-wrap items-center justify-between gap-3">
        <nav class="flex flex-wrap gap-1 rounded-lg bg-subtle p-1" aria-label="{{ __('Filter profiles') }}">
            <a href="{{ route('admin.radar.index', array_filter([...$filters, 'profile' => null, 'page' => null])) }}" @if (! $profile) aria-current="page" @endif
               class="rounded-md px-3 py-1.5 text-sm font-semibold {{ ! $profile ? 'bg-surface text-fg shadow-xs' : 'text-fg-secondary hover:text-fg' }}">{{ __('All projects') }}</a>
            @foreach ($profiles as $item)
                <a href="{{ route('admin.radar.index', array_filter([...$filters, 'profile' => $item->id, 'page' => null])) }}" @if ($profile?->is($item)) aria-current="page" @endif
                   class="rounded-md px-3 py-1.5 text-sm font-semibold {{ $profile?->is($item) ? 'bg-surface text-fg shadow-xs' : 'text-fg-secondary hover:text-fg' }}" dir="auto">{{ $item->name }}</a>
            @endforeach
        </nav>
        @can('radar.manage')
            <x-ui.button variant="secondary" size="sm" :href="$profile ? route('admin.radar.filters.edit', $profile) : route('admin.radar.filters.create')">{{ $profile ? __('Edit filter profile') : __('New filter profile') }}</x-ui.button>
        @endcan
    </div>

    <form method="GET" class="flex flex-wrap items-end gap-3" data-auto-submit>
        @if ($profile)<input type="hidden" name="profile" value="{{ $profile->id }}">@endif
        <div class="w-56"><x-ui.input name="q" type="search" :label="__('Search')" :value="$filters['q'] ?? null" maxlength="100" /></div>
        <div class="w-44"><x-ui.select name="status" :label="__('Status')" :options="\App\Enums\RadarProjectStatus::options()" :value="$filters['status'] ?? null" :placeholder="__('All except ignored')" /></div>
        <div class="w-44"><x-ui.select name="platform" :label="__('Platform')" :options="$platforms->pluck('name', 'key')->all()" :value="$filters['platform'] ?? null" :placeholder="__('All platforms')" /></div>
        <div class="w-32"><x-ui.input name="min_score" type="number" min="0" max="100" :label="__('Min. score')" :value="$filters['min_score'] ?? null" /></div>
        <div class="w-40"><x-ui.select name="sort" :label="__('Sort')" :options="['score' => __('Best score'), 'newest' => __('Newest')]" :value="$filters['sort'] ?? 'score'" /></div>
        <x-ui.button variant="secondary" size="sm">{{ __('Filter') }}</x-ui.button>
    </form>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
        <div class="flex flex-col gap-4">
            @forelse ($projects as $project)
                <x-ui.card class="{{ $loop->first ? 'ring-2 ring-brand/40' : '' }}" data-radar-project="{{ $project->id }}">
                    <div class="flex items-start gap-4">
                        @include('admin.radar._score', ['score' => $project->score])
                        <div class="flex min-w-0 flex-1 flex-col gap-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <x-ui.badge :dot="false">{{ $project->platform?->name }}</x-ui.badge>
                                <x-ui.badge :tone="$project->status->tone()">{{ $project->status->label() }}</x-ui.badge>
                                @if ($project->alerts->firstWhere('status', \App\Models\RadarAlert::STATUS_SENT))
                                    <x-ui.badge tone="info" :dot="false">{{ __('Telegram alert sent') }}</x-ui.badge>
                                @endif
                                @if ($project->ai_score !== null)
                                    <x-ui.badge tone="accent" :dot="false">{{ __('AI :score', ['score' => $project->ai_score]) }}</x-ui.badge>
                                @endif
                            </div>
                            <a href="{{ route('admin.radar.projects.show', $project) }}" class="text-base font-semibold text-fg hover:text-fg-brand" dir="auto">{{ $project->title }}</a>
                            <p class="text-xs text-fg-tertiary">
                                {{ $project->budgetLabel() ?? __('Budget not stated') }}
                                · {{ ($project->posted_at ?? $project->collected_at)?->diffForHumans() }}
                                @if ($project->proposals_count !== null) · {{ trans_choice(':count proposal|:count proposals', $project->proposals_count, ['count' => $project->proposals_count]) }} @endif
                            </p>
                            @if ($project->skillList() !== [])
                                <div class="flex flex-wrap gap-1.5" dir="ltr">
                                    @foreach (array_slice($project->skillList(), 0, 8) as $skill)
                                        <span class="rounded-full border border-line px-2.5 py-0.5 text-xs font-semibold text-fg">{{ $skill }}</span>
                                    @endforeach
                                </div>
                            @endif
                            @if ($project->aiEvaluation?->explanation)
                                <p class="rounded-md bg-subtle px-3 py-2 text-sm text-fg-secondary" dir="auto">{{ $project->aiEvaluation->explanation }}</p>
                            @endif
                            <div class="flex flex-wrap items-center gap-3 text-sm">
                                <a href="{{ route('admin.radar.projects.show', $project) }}" class="font-semibold text-fg-brand hover:underline">{{ __('Details & proposal') }}</a>
                                <a href="{{ $project->url }}" target="_blank" rel="noopener noreferrer nofollow" class="font-semibold text-fg-brand hover:underline">{{ __('Open on :platform', ['platform' => $project->platform?->name]) }}</a>
                            </div>
                        </div>
                    </div>
                </x-ui.card>
            @empty
                <x-ui.card>
                    <x-ui.empty-state :title="__('No projects yet')" :description="__('Projects appear here once a connector is active: save API credentials in Settings → Freelance Radar, or forward alert emails to an inbound n8n endpoint.')" />
                </x-ui.card>
            @endforelse

            {{ $projects->links() }}
        </div>

        <div class="flex flex-col gap-6">
            @if ($featured)
                @php($featured->loadMissing('ruleEvaluation'))
                <x-ui.card :title="__('Why :score? Score breakdown', ['score' => $featured->score ?? '—'])">
                    @include('admin.radar._breakdown', ['evaluation' => $featured->ruleEvaluation])
                    <p class="mt-4 text-xs text-fg-tertiary">{{ __('Alerts: ≥:immediate immediate · :normal–:normalMax normal · :digest–:digestMax daily digest · below :digest saved only. Thresholds are configurable. Proposals are drafts — never auto-submitted.', ['immediate' => $thresholds['immediate'], 'normal' => $thresholds['normal'], 'normalMax' => $thresholds['immediate'] - 1, 'digest' => $thresholds['digest'], 'digestMax' => $thresholds['normal'] - 1]) }}</p>
                </x-ui.card>
            @endif
        </div>
    </div>
</x-layouts.admin>
