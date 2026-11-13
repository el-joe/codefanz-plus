@php
    $factorLabels = [
        'skills' => __('Technical skills'),
        'budget' => __('Budget suitability'),
        'client' => __('Client quality'),
        'freshness' => __('Freshness'),
        'competition' => __('Competition'),
        'language' => __('Language'),
    ];
@endphp

<x-layouts.admin :title="__('Radar settings & filters')" :crumb="__('Freelance Radar')">
    @include('admin.radar._nav')

    @if ($errors->any())
        <x-ui.alert tone="danger" :title="__('Please fix the highlighted fields')">{{ $errors->first('weights') ?: __('Some fields need attention.') }}</x-ui.alert>
    @endif

    <x-ui.card padding="p-0" class="overflow-x-auto">
        <div class="flex flex-wrap items-center justify-between gap-3 px-5 pt-5 pb-4">
            <h2 class="text-base font-semibold text-fg">{{ __('Platforms & connectors') }}</h2>
            @can('settings.manage')
                <a href="{{ route('admin.settings.edit', 'radar') }}" class="text-sm font-semibold text-fg-brand hover:underline">{{ __('API credentials & Telegram bot') }}</a>
            @endcan
        </div>
        <table class="w-full text-sm">
            <thead class="bg-subtle text-xs text-fg-tertiary">
                <tr>
                    <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Platform') }}</th>
                    <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Status') }}</th>
                    <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Last run') }}</th>
                    <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Switch, cadence & limit') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($platforms as $platform)
                    @php($status = $platform->displayStatus())
                    <tr class="border-t border-line-subtle align-top" data-platform-row="{{ $platform->key }}">
                        <td class="px-4 py-3">
                            <p class="font-semibold text-fg">{{ $platform->name }}</p>
                            <p class="text-xs text-fg-tertiary">{{ $platform->connector->label() }}</p>
                            <p class="mt-1 max-w-sm text-xs text-fg-secondary">{{ __($platform->status_note ?? '') }}</p>
                        </td>
                        <td class="px-4 py-3"><x-ui.badge :tone="$status->tone()">{{ $status->label() }}</x-ui.badge>
                            @if ($platform->last_error)<p class="mt-1 max-w-xs text-xs text-fg-danger">{{ $platform->last_error }}</p>@endif
                        </td>
                        <td class="px-4 py-3 text-fg-secondary">
                            {{ $platform->last_run_at?->diffForHumans() ?? __('Never') }}
                            @if ($platform->last_run_at)<span class="block text-xs">{{ trans_choice(':count project|:count projects', $platform->last_run_count, ['count' => $platform->last_run_count]) }}</span>@endif
                        </td>
                        <td class="px-4 py-3">
                            @if ($platform->connector !== \App\Enums\RadarConnectorType::None)
                                <form method="POST" action="{{ route('admin.radar.platforms.update', $platform) }}" class="flex flex-wrap items-end gap-2">
                                    @csrf
                                    @method('PUT')
                                    <label class="flex items-center gap-2 text-sm text-fg">
                                        <input type="hidden" name="is_enabled" value="0">
                                        <input type="checkbox" name="is_enabled" value="1" @checked($platform->is_enabled) class="size-4 accent-brand">
                                        {{ __('On') }}
                                    </label>
                                    <div class="w-24"><x-ui.input :id="'interval-'.$platform->key" name="fetch_interval_minutes" type="number" min="5" max="1440" :label="__('Every (min)')" :value="$platform->fetch_interval_minutes" /></div>
                                    <div class="w-24"><x-ui.input :id="'limit-'.$platform->key" name="rate_limit_per_hour" type="number" min="0" max="1000" :label="__('Max / hour')" :value="$platform->rate_limit_per_hour" /></div>
                                    <x-ui.button variant="secondary" size="sm">{{ __('Save') }}</x-ui.button>
                                </form>
                                @if ($platform->connector === \App\Enums\RadarConnectorType::Api)
                                    <form method="POST" action="{{ route('admin.radar.platforms.fetch', $platform) }}" class="mt-2">
                                        @csrf
                                        <button type="submit" class="text-xs font-semibold text-fg-brand hover:underline">{{ __('Fetch now') }}</button>
                                    </form>
                                @endif
                            @else
                                <p class="text-xs text-fg-tertiary">{{ __('Not collected: no permitted integration.') }}</p>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p class="px-5 py-4 text-xs text-fg-tertiary">{{ __('Only official APIs and alert emails the platforms send to you are used. Nothing is scraped, and no login, CAPTCHA or terms of service are bypassed. Email platforms become active once an inbound n8n endpoint with the “Radar: import a platform alert email” action is enabled.') }}</p>
    </x-ui.card>

    <div class="grid gap-6 xl:grid-cols-[1fr_380px]">
        <form method="POST" action="{{ route('admin.radar.settings.update') }}" class="flex flex-col gap-6">
            @csrf
            @method('PUT')

            <x-ui.card :title="__('Scoring weights')">
                <p class="mb-4 text-sm text-fg-secondary">{{ __('Each factor scores 0–100; the rule score is their weighted average. A weight of 0 ignores the factor.') }}</p>
                <div class="grid gap-4 sm:grid-cols-3">
                    @foreach ($factorLabels as $factor => $label)
                        <x-ui.input :id="'weight-'.$factor" :name="'weights['.$factor.']'" type="number" min="0" max="100" :label="$label" :value="$settings['weights'][$factor]" required />
                    @endforeach
                </div>
            </x-ui.card>

            <x-ui.card :title="__('Alert thresholds')">
                <div class="grid gap-4 sm:grid-cols-3">
                    <x-ui.input id="threshold-immediate" name="thresholds[immediate]" type="number" min="1" max="100" :label="__('Immediate alert from')" :value="$settings['thresholds']['immediate']" required />
                    <x-ui.input id="threshold-normal" name="thresholds[normal]" type="number" min="1" max="100" :label="__('Normal alert from')" :value="$settings['thresholds']['normal']" required />
                    <x-ui.input id="threshold-digest" name="thresholds[digest]" type="number" min="1" max="100" :label="__('Daily digest from')" :value="$settings['thresholds']['digest']" required />
                </div>
                <p class="mt-3 text-xs text-fg-tertiary">{{ __('Below the digest threshold projects are saved without a notification. An unchanged project never alerts twice.') }}</p>
            </x-ui.card>

            <x-ui.card :title="__('What we look for')">
                <div class="flex flex-col gap-4">
                    @include('admin._textarea', ['name' => 'keywords', 'id' => 'keywords', 'label' => __('Extra skills and keywords'), 'value' => implode("\n", $settings['keywords']), 'rows' => 4, 'help' => __('One per line. Added to every technology on the website when matching skills.')])
                    <div class="grid gap-4 sm:grid-cols-3">
                        <x-ui.input name="target_budget_usd" type="number" min="1" :label="__('Target fixed budget (USD)')" :value="$settings['target_budget_usd']" required />
                        <x-ui.input name="target_hourly_usd" type="number" min="1" :label="__('Target hourly rate (USD)')" :value="$settings['target_hourly_usd']" required />
                        <x-ui.input name="digest_size" type="number" min="1" max="50" :label="__('Projects in the daily digest')" :value="$settings['digest_size']" required />
                    </div>
                    <fieldset>
                        <legend class="mb-2 text-sm font-semibold text-fg">{{ __('Working languages') }}</legend>
                        <div class="flex flex-wrap gap-4">
                            @foreach ($languages as $code => $name)
                                <label class="flex items-center gap-2 text-sm text-fg"><input type="checkbox" name="languages[]" value="{{ $code }}" @checked(in_array($code, $settings['languages'], true)) class="size-4 accent-brand"> {{ $name }}</label>
                            @endforeach
                        </div>
                        @error('languages')<p class="mt-1 text-sm text-fg-danger">{{ $message }}</p>@enderror
                    </fieldset>
                    <label class="flex items-start gap-3">
                        <input type="hidden" name="ai_auto_score" value="0">
                        <input type="checkbox" name="ai_auto_score" value="1" @checked($settings['ai_auto_score']) class="mt-0.5 size-4 accent-brand">
                        <span class="flex flex-col">
                            <span class="text-sm font-semibold text-fg">{{ __('Add an AI relevance score automatically') }}</span>
                            <span class="text-xs text-fg-tertiary">{{ __('For projects at or above the digest threshold. Uses the AI budget; paused by the Freelance Radar AI emergency control.') }}</span>
                        </span>
                    </label>
                </div>
            </x-ui.card>

            <div class="flex flex-wrap gap-3"><x-ui.button>{{ __('Save settings') }}</x-ui.button></div>
        </form>

        <aside class="flex flex-col gap-6">
            <x-ui.card :title="__('Telegram alerts')" data-telegram-card>
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm text-fg">{{ __('Bot status') }}</p>
                    @if (! $telegramConfigured)
                        <x-ui.badge>{{ __('Requires credentials') }}</x-ui.badge>
                    @elseif ($telegramVerifiedAt)
                        <x-ui.badge tone="success">{{ __('Test message delivered') }}</x-ui.badge>
                    @else
                        <x-ui.badge tone="info">{{ __('Credentials saved · not verified') }}</x-ui.badge>
                    @endif
                </div>
                @if ($telegramConfigured)
                    <p class="mt-2 text-xs text-fg-tertiary" dir="ltr">{{ __('Chat') }}: {{ $telegramChat }}</p>
                @endif
                @if ($telegramVerifiedAt)
                    <p class="mt-1 text-xs text-fg-tertiary">{{ __('Last successful test :time', ['time' => \Carbon\Carbon::parse($telegramVerifiedAt)->diffForHumans()]) }}</p>
                @endif
                <form method="POST" action="{{ route('admin.radar.telegram.test') }}" class="mt-4">
                    @csrf
                    <x-ui.button variant="secondary" size="sm" :disabled="! $telegramConfigured">{{ __('Send test message') }}</x-ui.button>
                </form>
                <p class="mt-3 text-xs text-fg-tertiary">{{ __('Immediate and normal alerts are sent as projects arrive; the digest is sent daily at :time.', ['time' => config('codefanz.radar.digest_time')]) }}</p>
            </x-ui.card>

            <x-ui.card :title="__('Re-score')">
                <p class="text-sm text-fg-secondary">{{ __('Apply the current weights to projects collected in the last 14 days. Projects that already alerted do not alert again.') }}</p>
                <form method="POST" action="{{ route('admin.radar.rescore') }}" class="mt-3">
                    @csrf
                    <x-ui.button variant="secondary" size="sm">{{ __('Re-score recent projects') }}</x-ui.button>
                </form>
            </x-ui.card>
        </aside>
    </div>

    <x-ui.card padding="p-0" class="overflow-x-auto">
        <div class="flex items-center justify-between gap-3 px-5 pt-5 pb-4">
            <h2 class="text-base font-semibold text-fg">{{ __('Filter profiles') }}</h2>
            <x-ui.button size="sm" :href="route('admin.radar.filters.create')">{{ __('New filter profile') }}</x-ui.button>
        </div>
        @if ($filters->isEmpty())
            <x-ui.empty-state :title="__('No filter profiles yet')" :description="__('Profiles such as “Laravel & SaaS” or “Flutter” become tabs on the radar. While none has alerts on, every project above the thresholds alerts.')" />
        @else
            <table class="w-full text-sm">
                <thead class="bg-subtle text-xs text-fg-tertiary">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Name') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Rules') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Alerts') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($filters as $filter)
                        <tr class="border-t border-line-subtle">
                            <td class="px-4 py-3"><a href="{{ route('admin.radar.filters.edit', $filter) }}" class="font-semibold text-fg hover:text-fg-brand" dir="auto">{{ $filter->name }}</a>
                                @unless ($filter->is_active)<x-ui.badge class="ms-2">{{ __('Inactive') }}</x-ui.badge>@endunless
                            </td>
                            <td class="px-4 py-3 text-fg-secondary" dir="auto">{{ collect([...$filter->ruleList('include_keywords'), ...$filter->ruleList('skills')])->take(6)->implode(', ') ?: '—' }}</td>
                            <td class="px-4 py-3"><x-ui.badge :tone="$filter->alerts_enabled ? 'success' : 'neutral'">{{ $filter->alerts_enabled ? __('On') : __('Off') }}</x-ui.badge></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-ui.card>
</x-layouts.admin>
