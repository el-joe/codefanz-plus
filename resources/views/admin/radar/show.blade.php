@php
    $thresholds = app(\App\Services\Radar\RadarSettings::class)->thresholds();
    $flagTypes = collect(\App\Enums\GuardrailFlagType::cases())->keyBy('value');
@endphp

<x-layouts.admin :title="$project->title" :crumb="__('Freelance Radar')">
    @include('admin.radar._nav')

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
        <div class="flex flex-col gap-6">
            <x-ui.card>
                <div class="flex items-start gap-4">
                    @include('admin.radar._score', ['score' => $project->score])
                    <div class="flex min-w-0 flex-1 flex-col gap-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <x-ui.badge :dot="false">{{ $project->platform?->name }}</x-ui.badge>
                            <x-ui.badge :tone="$project->status->tone()" data-radar-status>{{ $project->status->label() }}</x-ui.badge>
                            <x-ui.badge :dot="false">{{ $project->budget_type->label() }}</x-ui.badge>
                            <x-ui.badge :dot="false">{{ $project->language === 'ar' ? __('Arabic') : __('English') }}</x-ui.badge>
                        </div>
                        <h2 class="text-lg font-semibold text-fg" dir="auto">{{ $project->title }}</h2>
                        <dl class="grid grid-cols-1 gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
                            <div class="flex gap-2"><dt class="text-fg-tertiary">{{ __('Budget') }}</dt><dd class="text-fg">{{ $project->budgetLabel() ?? __('Not stated') }}</dd></div>
                            <div class="flex gap-2"><dt class="text-fg-tertiary">{{ __('Posted') }}</dt><dd class="text-fg">{{ ($project->posted_at ?? $project->collected_at)?->diffForHumans() }}</dd></div>
                            <div class="flex gap-2"><dt class="text-fg-tertiary">{{ __('Proposals') }}</dt><dd class="text-fg">{{ $project->proposals_count ?? '—' }}</dd></div>
                            <div class="flex gap-2"><dt class="text-fg-tertiary">{{ __('Client') }}</dt><dd class="text-fg">{{ collect([$project->client_country, $project->client_rating !== null ? '★ '.number_format($project->client_rating, 1) : null, $project->client_payment_verified ? __('payment verified') : null])->filter()->implode(' · ') ?: '—' }}</dd></div>
                        </dl>
                        <div>
                            <a href="{{ $project->url }}" target="_blank" rel="noopener noreferrer nofollow" class="text-sm font-semibold text-fg-brand hover:underline">{{ __('Open on :platform', ['platform' => $project->platform?->name]) }}</a>
                        </div>
                    </div>
                </div>
                @if ($project->skillList() !== [])
                    <div class="mt-4 flex flex-wrap gap-1.5" dir="ltr">
                        @foreach ($project->skillList() as $skill)
                            <span class="rounded-full border px-2.5 py-0.5 text-xs font-semibold {{ in_array($skill, $matchingSkills, true) ? 'border-line-success bg-success-subtle text-fg-success' : 'border-line text-fg' }}">{{ $skill }}</span>
                        @endforeach
                    </div>
                @endif
                @if (filled($project->description))
                    <div class="mt-4 whitespace-pre-line rounded-md bg-subtle p-4 text-sm text-fg-secondary" dir="auto">{{ $project->description }}</div>
                @endif
                <p class="mt-3 text-xs text-fg-tertiary">{{ __('Collected :time from :platform. Listing text is shown as plain text.', ['time' => $project->collected_at?->diffForHumans(), 'platform' => $project->platform?->connector->label()]) }}</p>
            </x-ui.card>

            <x-ui.card :title="__('Proposal drafts')">
                <x-ui.alert tone="info" class="mb-4">{{ __('Drafts are never submitted automatically. Review, edit and copy the text, then send it on the platform yourself.') }}</x-ui.alert>

                @forelse ($project->proposals->sortByDesc('id') as $proposal)
                    <div id="proposal-{{ $proposal->id }}" class="mb-6 flex flex-col gap-3 border-b border-line-subtle pb-6 last:mb-0 last:border-0 last:pb-0" data-radar-proposal>
                        <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-fg-tertiary">
                            <span>{{ __(':author · :time · :language', ['author' => $proposal->author?->name ?? __('Unknown'), 'time' => $proposal->created_at->diffForHumans(), 'language' => strtoupper($proposal->locale)]) }}</span>
                            <button type="button" data-copy="{{ $proposal->body }}" data-copied-label="{{ __('Copied') }}" class="rounded-md border border-line px-2 py-1 text-xs font-semibold text-fg hover:bg-subtle">{{ __('Copy text') }}</button>
                        </div>
                        @if ($proposal->guardrail_flags)
                            <x-ui.alert tone="warning" :title="__('Check before sending')">
                                <ul class="list-disc ps-5">
                                    @foreach ($proposal->guardrail_flags as $flag)
                                        <li>{{ $flagTypes[$flag['type']]?->label() ?? $flag['type'] }}: <span dir="auto">“{{ $flag['excerpt'] }}”</span> — {{ $flagTypes[$flag['type']]?->hint() }}</li>
                                    @endforeach
                                </ul>
                            </x-ui.alert>
                        @endif
                        @can('radar.manage')
                            <form method="POST" action="{{ route('admin.radar.projects.proposals.update', [$project, $proposal]) }}" class="flex flex-col gap-2">
                                @csrf
                                @method('PUT')
                                <label for="proposal-body-{{ $proposal->id }}" class="sr-only">{{ __('Proposal text') }}</label>
                                <textarea id="proposal-body-{{ $proposal->id }}" name="body" rows="10" maxlength="10000" dir="{{ $proposal->locale === 'ar' ? 'rtl' : 'ltr' }}" class="rounded-md border border-line-strong bg-surface px-3 py-2 text-sm text-fg">{{ $proposal->body }}</textarea>
                                <div><x-ui.button variant="secondary" size="sm">{{ __('Save draft') }}</x-ui.button></div>
                            </form>
                        @else
                            <div class="whitespace-pre-line text-sm text-fg" dir="{{ $proposal->locale === 'ar' ? 'rtl' : 'ltr' }}">{{ $proposal->body }}</div>
                        @endcan
                    </div>
                @empty
                    <p class="text-sm text-fg-secondary">{{ __('No proposal drafts yet.') }}</p>
                @endforelse

                @can('radar.manage')
                    <form method="POST" action="{{ route('admin.radar.projects.proposals.store', $project) }}" class="mt-6 flex flex-col gap-4 rounded-lg border border-line p-4">
                        @csrf
                        <h3 class="text-sm font-semibold text-fg">{{ __('Draft a proposal with AI') }}</h3>
                        @error('ai')<x-ui.alert tone="danger">{{ $message }}</x-ui.alert>@enderror
                        @if ($aiReason)
                            <x-ui.alert tone="warning">{{ __('AI is unavailable: :reason', ['reason' => $aiReason->label()]) }}</x-ui.alert>
                        @endif
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <x-ui.select name="locale" :label="__('Language')" :options="$locales" :value="$project->language" />
                            <x-ui.input name="price" :label="__('Price or rate to quote')" maxlength="100" :helper="__('Leave empty: the draft will not mention any price.')" />
                        </div>
                        @include('admin._textarea', ['name' => 'facts', 'id' => 'facts', 'label' => __('Facts about our experience'), 'value' => '', 'rows' => 3, 'max' => 2000, 'help' => __('Only these facts may be mentioned. Claims about portfolio, clients or years of experience that are not here are flagged.')])
                        <div><x-ui.button :disabled="$aiReason !== null">{{ __('Generate draft') }}</x-ui.button></div>
                    </form>
                @endcan
            </x-ui.card>
        </div>

        <div class="flex flex-col gap-6">
            <x-ui.card :title="__('Rule score: :score / 100', ['score' => $project->score ?? '—'])">
                @include('admin.radar._breakdown', ['evaluation' => $project->ruleEvaluation])
                <p class="mt-4 text-xs text-fg-tertiary">{{ __('Weighted average of transparent rules. Weights and thresholds are set on Radar → Settings.') }}</p>
            </x-ui.card>

            <x-ui.card :title="__('AI relevance score')" data-ai-score>
                @if ($project->aiEvaluation)
                    <p class="text-3xl font-semibold text-fg">{{ $project->aiEvaluation->score }}<span class="text-base text-fg-tertiary"> / 100</span></p>
                    <p class="mt-2 text-sm text-fg-secondary" dir="auto">{{ $project->aiEvaluation->explanation }}</p>
                    @if (($project->aiEvaluation->breakdown['matching_skills'] ?? []) !== [])
                        <p class="mt-2 text-xs text-fg-tertiary" dir="auto">{{ __('Matching skills: :skills', ['skills' => implode(', ', $project->aiEvaluation->breakdown['matching_skills'])]) }}</p>
                    @endif
                    <p class="mt-2 text-xs text-fg-tertiary">{{ $project->aiEvaluation->model }} · {{ $project->aiEvaluation->created_at?->diffForHumans() }}</p>
                @else
                    <p class="text-sm text-fg-secondary">{{ __('Not scored by AI yet.') }}</p>
                @endif
                <p class="mt-3 text-xs text-fg-tertiary">{{ __('Shown next to the rule score; it never replaces it or changes the alert level.') }}</p>
                @can('radar.manage')
                    <form method="POST" action="{{ route('admin.radar.projects.ai-score', $project) }}" class="mt-3">
                        @csrf
                        <x-ui.button variant="secondary" size="sm" :disabled="$aiReason !== null">{{ $project->aiEvaluation ? __('Score again with AI') : __('Score with AI') }}</x-ui.button>
                    </form>
                    @if ($aiReason)
                        <p class="mt-2 text-xs text-fg-warning">{{ $aiReason->label() }}</p>
                    @endif
                @endcan
            </x-ui.card>

            @can('radar.manage')
                <x-ui.card :title="__('Status')">
                    <form method="POST" action="{{ route('admin.radar.projects.status', $project) }}" class="flex flex-col gap-3">
                        @csrf
                        <x-ui.select name="status" :label="__('Pipeline status')" :options="$statuses" :value="$project->status->value" />
                        <x-ui.button variant="secondary" size="sm">{{ __('Update status') }}</x-ui.button>
                    </form>
                </x-ui.card>

                <x-ui.card :title="__('CRM')">
                    @if ($project->lead)
                        <p class="text-sm text-fg">{{ __('Converted to lead') }} <a href="{{ route('admin.leads.show', $project->lead) }}" class="font-semibold text-fg-brand hover:underline">{{ $project->lead->reference }}</a></p>
                    @elseif ($canConvert)
                        <form method="POST" action="{{ route('admin.radar.projects.convert', $project) }}" class="flex flex-col gap-3">
                            @csrf
                            <x-ui.input name="name" :label="__('Client name')" :value="__('Client on :platform', ['platform' => $project->platform?->name])" required maxlength="120" />
                            <x-ui.input name="email" type="email" :label="__('Client email')" :value="$placeholderEmail" required dir="ltr" :helper="__('Platforms rarely share emails. The .invalid placeholder never receives mail; replace it when you have the real address.')" />
                            <x-ui.input name="company" :label="__('Company')" maxlength="160" />
                            <x-ui.select name="preferred_locale" :label="__('Language')" :options="$locales" :value="$project->language" />
                            <x-ui.button size="sm">{{ __('Convert to lead') }}</x-ui.button>
                        </form>
                    @else
                        <p class="text-sm text-fg-secondary">{{ __('Converting to a lead needs the leads.manage permission.') }}</p>
                    @endif
                </x-ui.card>
            @endcan

            @if ($project->alerts->isNotEmpty())
                <x-ui.card :title="__('Telegram alerts')">
                    <ul class="flex flex-col gap-2 text-sm">
                        @foreach ($project->alerts as $alert)
                            <li class="flex items-center justify-between gap-2">
                                <span class="text-fg">{{ $alert->level->label() }}</span>
                                <x-ui.badge :tone="match ($alert->status) { 'sent' => 'success', 'failed' => 'danger', 'pending' => 'info', default => 'neutral' }" title="{{ $alert->error }}">{{ __(\Illuminate\Support\Str::headline($alert->status)) }}</x-ui.badge>
                            </li>
                        @endforeach
                    </ul>
                </x-ui.card>
            @endif
        </div>
    </div>
</x-layouts.admin>
