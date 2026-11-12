@php
    $canManage = auth()->user()->can('update', $lead);
    $languages = \App\Models\Language::active();
    $activityIcon = [
        'note' => 'bg-brand', 'call' => 'bg-fg-info', 'email' => 'bg-fg-info', 'meeting' => 'bg-accent',
        'status_change' => 'bg-fg-warning', 'assignment' => 'bg-fg-success', 'system' => 'bg-fg-tertiary',
    ];
    $statusLabel = fn (?string $value) => \App\Enums\LeadStatus::tryFrom((string) $value)?->label() ?? $value;
@endphp

<x-layouts.admin :title="$lead->name" :crumb="__('Leads')">
    @if ($errors->any())
        <x-ui.alert tone="danger" :title="__('Please fix the highlighted fields')">
            {{ $errors->first('lock_version') ?: $errors->first('status') ?: $errors->first('lost_reason') ?: $errors->first('assigned_to') ?: __('Some fields need attention.') }}
        </x-ui.alert>
    @endif

    <div class="flex flex-wrap items-center gap-3">
        <span class="font-mono text-sm text-fg-tertiary" dir="ltr">{{ $lead->reference }}</span>
        <x-ui.badge :tone="$lead->status->tone()">{{ $lead->status->label() }}</x-ui.badge>
        <x-ui.badge :tone="$lead->priority->tone()" :dot="false">{{ __('Priority: :priority', ['priority' => $lead->priority->label()]) }}</x-ui.badge>
        <x-ui.badge tone="neutral" :dot="false">{{ $lead->source->label() }}</x-ui.badge>
        <div class="flex-1"></div>
        <a href="{{ route('admin.leads.index') }}" class="text-sm font-semibold text-fg-brand hover:underline">{{ __('Back to leads') }}</a>
        @if ($canManage)
            <x-ui.button variant="secondary" size="sm" :href="route('admin.leads.edit', $lead)">{{ __('Edit details') }}</x-ui.button>
        @endif
    </div>

    @if ($lead->possibleDuplicateOf)
        <x-ui.alert tone="warning" :title="__('Possible duplicate')">
            {{ __('Another open lead uses this email address:') }}
            <a href="{{ route('admin.leads.show', $lead->possibleDuplicateOf) }}" class="font-semibold underline"><span dir="ltr">{{ $lead->possibleDuplicateOf->reference }}</span> · <span dir="auto">{{ $lead->possibleDuplicateOf->name }}</span></a>.
            {{ __('Nothing was merged automatically.') }}
        </x-ui.alert>
    @endif

    <div class="grid gap-6 xl:grid-cols-[1fr_360px]">
        <div class="flex min-w-0 flex-col gap-6">
            <x-ui.card :title="__('Project requirements')">
                <dl class="grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                    @if ($lead->services->isNotEmpty())
                        <div class="sm:col-span-2">
                            <dt class="text-fg-tertiary">{{ __('Services') }}</dt>
                            <dd class="mt-1 flex flex-wrap gap-1.5">
                                @foreach ($lead->services as $service)
                                    <x-ui.badge tone="brand" :dot="false">{{ $service->translation()?->title }}</x-ui.badge>
                                @endforeach
                            </dd>
                        </div>
                    @endif
                    @if ($lead->platforms)
                        <div>
                            <dt class="text-fg-tertiary">{{ __('Required platforms') }}</dt>
                            <dd class="mt-1 font-semibold text-fg">{{ collect($lead->platforms)->map(fn ($platform) => \App\Enums\ProjectPlatform::tryFrom($platform)?->label() ?? $platform)->implode(', ') }}</dd>
                        </div>
                    @endif
                    @if ($lead->technologies->isNotEmpty())
                        <div>
                            <dt class="text-fg-tertiary">{{ __('Preferred technologies') }}</dt>
                            <dd class="mt-1 font-semibold text-fg">{{ $lead->technologies->map(fn ($technology) => $technology->translation()?->name)->filter()->implode(', ') }}</dd>
                        </div>
                    @endif
                    @foreach ([
                        __('Estimated budget') => $lead->budget_range?->label(),
                        __('Timeline') => $lead->timeline?->label(),
                        __('Expected deadline') => $lead->deadline?->translatedFormat('j M Y'),
                        __('Estimated value') => $lead->formattedEstimatedValue(),
                        __('Reference from the site') => $lead->brief['reference'] ?? null,
                        __('Contact form subject') => isset($lead->brief['topic']) ? \App\Enums\ContactTopic::tryFrom($lead->brief['topic'])?->label() : null,
                    ] as $label => $value)
                        @if (filled($value))
                            <div>
                                <dt class="text-fg-tertiary">{{ $label }}</dt>
                                <dd class="mt-1 font-semibold text-fg" dir="auto">{{ $value }}</dd>
                            </div>
                        @endif
                    @endforeach
                    @if ($lead->existing_url)
                        <div>
                            <dt class="text-fg-tertiary">{{ __('Existing website') }}</dt>
                            <dd class="mt-1"><a href="{{ $lead->existing_url }}" target="_blank" rel="noopener noreferrer nofollow" class="font-semibold text-fg-brand hover:underline" dir="ltr">{{ $lead->existing_url }}</a></dd>
                        </div>
                    @endif
                </dl>

                @foreach ([__('Project description') => $lead->description, __('Main features') => $lead->features, __('Business goals') => $lead->goals] as $label => $text)
                    @if (filled($text))
                        <div class="mt-5">
                            <h3 class="text-sm text-fg-tertiary">{{ $label }}</h3>
                            <p class="mt-1 whitespace-pre-line text-base leading-relaxed text-fg" dir="auto">{{ $text }}</p>
                        </div>
                    @endif
                @endforeach
            </x-ui.card>

            <x-ui.card :title="__('Attachments')">
                @if ($lead->attachments->isEmpty())
                    <p class="text-sm text-fg-secondary">{{ __('No files were attached.') }}</p>
                @else
                    <ul class="flex flex-col divide-y divide-line-subtle text-sm" data-lead-attachments>
                        @foreach ($lead->attachments as $attachment)
                            <li class="flex items-center gap-3 py-2.5">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" class="shrink-0 text-fg-tertiary"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>
                                <span class="min-w-0 flex-1 truncate text-fg" dir="auto">{{ $attachment->original_name }}</span>
                                <span class="text-xs text-fg-tertiary" dir="ltr">{{ $attachment->humanSize() }}</span>
                                <a href="{{ route('admin.leads.attachments.show', [$lead, $attachment]) }}" class="font-semibold text-fg-brand hover:underline">{{ __('Download') }}<span class="sr-only"> {{ $attachment->original_name }}</span></a>
                            </li>
                        @endforeach
                    </ul>
                    <p class="mt-3 text-xs text-fg-tertiary">{{ __('Files are stored privately. Open unknown files with care.') }}</p>
                @endif
            </x-ui.card>

            <x-ui.card :title="__('Activity')">
                @if ($canManage)
                    <form method="POST" action="{{ route('admin.leads.activities.store', $lead) }}" class="mb-6 flex flex-col gap-3 rounded-lg border border-line p-4">
                        @csrf
                        <div class="grid gap-3 sm:grid-cols-[200px_1fr]">
                            <x-ui.select name="type" :label="__('Type')" :options="\App\Enums\LeadActivityType::manualOptions()" />
                            <div class="flex flex-col gap-1.5">
                                <label for="activity-body" class="text-sm font-semibold text-fg">{{ __('Details') }}</label>
                                <textarea id="activity-body" name="body" rows="3" required maxlength="5000" dir="auto"
                                          class="w-full rounded-md border bg-surface px-3 py-2.5 text-base text-fg focus:border-line-focus focus:outline-none focus:ring-4 focus:ring-brand/20 {{ $errors->has('body') ? 'border-line-danger' : 'border-line' }}">{{ old('body') }}</textarea>
                                @error('body')<p class="text-sm text-fg-danger">{{ $message }}</p>@enderror
                            </div>
                        </div>
                        <x-ui.button size="sm" class="w-fit">{{ __('Log activity') }}</x-ui.button>
                    </form>
                @endif

                <ol class="flex flex-col gap-4" data-lead-timeline>
                    @foreach ($lead->activities as $activity)
                        <li class="flex gap-3">
                            <span class="mt-1.5 size-2.5 shrink-0 rounded-full {{ $activityIcon[$activity->type->value] ?? 'bg-brand' }}" aria-hidden="true"></span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm text-fg">
                                    @switch($activity->type)
                                        @case(\App\Enums\LeadActivityType::System)
                                            @if (($activity->meta['event'] ?? null) === 'created')
                                                {{ __('Lead created from :source', ['source' => \App\Enums\LeadSource::tryFrom($activity->meta['source'] ?? '')?->label() ?? '—']) }}
                                            @else
                                                {{ $activity->body }}
                                            @endif
                                            @break
                                        @case(\App\Enums\LeadActivityType::StatusChange)
                                            {{ __('Status changed from :from to :to', ['from' => $statusLabel($activity->meta['from'] ?? null), 'to' => $statusLabel($activity->meta['to'] ?? null)]) }}
                                            @break
                                        @case(\App\Enums\LeadActivityType::Assignment)
                                            {{ filled($activity->meta['to_name'] ?? null) ? __('Assigned to :name', ['name' => $activity->meta['to_name']]) : __('Unassigned') }}
                                            @break
                                        @default
                                            <span class="font-semibold">{{ $activity->type->label() }}</span>
                                    @endswitch
                                </p>
                                @if (filled($activity->body) && $activity->type !== \App\Enums\LeadActivityType::System)
                                    <p class="mt-1 whitespace-pre-line text-sm text-fg-secondary" dir="auto">{{ $activity->body }}</p>
                                @endif
                                <p class="mt-0.5 text-xs text-fg-tertiary">
                                    {{ $activity->created_at->translatedFormat('j M Y, H:i') }}
                                    · {{ $activity->user?->name ?? __('System') }}
                                </p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </x-ui.card>
        </div>

        <aside class="flex flex-col gap-6">
            <x-ui.card :title="__('Contact')">
                <dl class="flex flex-col divide-y divide-line-subtle text-sm">
                    @foreach (array_filter([
                        __('Email') => $lead->email,
                        __('Phone') => $lead->phone,
                        __('Company') => $lead->company,
                        __('Country') => $countryName,
                        __('Preferred contact') => $lead->preferred_contact->label(),
                        __('Reply in') => $languages[$lead->preferred_locale]['native_name'] ?? strtoupper($lead->preferred_locale),
                        __('Received') => $lead->created_at->translatedFormat('j M Y, H:i'),
                        __('Consent given') => $lead->consent_at?->translatedFormat('j M Y, H:i'),
                        __('Follow-up') => $lead->follow_up_at?->translatedFormat('j M Y, H:i'),
                        __('Added by') => $lead->creator?->name,
                    ]) as $label => $value)
                        <div class="flex justify-between gap-4 py-2.5">
                            <dt class="text-fg-tertiary">{{ $label }}</dt>
                            <dd class="text-end font-semibold text-fg break-all" @if (in_array($label, [__('Email'), __('Phone')], true)) dir="ltr" @else dir="auto" @endif>{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
                <div class="mt-4 flex flex-col gap-2">
                    <x-ui.button size="sm" :href="'mailto:'.$lead->email.'?subject='.rawurlencode($lead->reference)">{{ __('Reply by email') }}</x-ui.button>
                    @if ($lead->contactMessage)
                        <a href="{{ route('admin.contact-messages.show', $lead->contactMessage) }}" class="text-sm font-semibold text-fg-brand hover:underline">{{ __('View the original contact message') }}</a>
                    @endif
                </div>
            </x-ui.card>

            @if ($canManage)
                <x-ui.card :title="__('Status')">
                    @if ($lead->status === \App\Enums\LeadStatus::Lost && $lead->lost_reason)
                        <p class="mb-3 text-sm text-fg-secondary">{{ __('Lost because:') }} <span class="text-fg" dir="auto">{{ $lead->lost_reason }}</span></p>
                    @endif
                    @if ($lead->status->allowedTransitions())
                        @include('admin.leads._move-form', ['lead' => $lead])
                    @else
                        <p class="text-sm text-fg-secondary">{{ __('Won leads are final.') }}</p>
                    @endif
                </x-ui.card>

                <x-ui.card :title="__('Owner')">
                    <form method="POST" action="{{ route('admin.leads.assign', $lead) }}" class="flex flex-col gap-3">
                        @csrf
                        <input type="hidden" name="lock_version" value="{{ $lead->lock_version }}">
                        <x-ui.select name="assigned_to" :label="__('Assigned to')" :options="$assignees" :value="$lead->assigned_to" :placeholder="__('Unassigned')" />
                        <x-ui.button size="sm" variant="secondary" class="w-fit">{{ __('Save owner') }}</x-ui.button>
                    </form>
                </x-ui.card>
            @else
                <x-ui.card :title="__('Owner')">
                    <p class="text-sm text-fg">{{ $lead->assignee?->name ?? __('Unassigned') }}</p>
                    @if ($lead->status === \App\Enums\LeadStatus::Lost && $lead->lost_reason)
                        <p class="mt-3 text-sm text-fg-secondary">{{ __('Lost because:') }} <span class="text-fg" dir="auto">{{ $lead->lost_reason }}</span></p>
                    @endif
                </x-ui.card>
            @endif

            @if ($lead->utm_source || $lead->utm_medium || $lead->utm_campaign || $lead->referrer)
                <x-ui.card :title="__('Attribution')">
                    <dl class="flex flex-col gap-2 text-sm">
                        @foreach (array_filter(['utm_source' => $lead->utm_source, 'utm_medium' => $lead->utm_medium, 'utm_campaign' => $lead->utm_campaign, __('Referrer') => $lead->referrer]) as $label => $value)
                            <div class="flex justify-between gap-4">
                                <dt class="text-fg-tertiary" dir="ltr">{{ $label }}</dt>
                                <dd class="truncate text-end text-fg" dir="ltr">{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </x-ui.card>
            @endif
        </aside>
    </div>
</x-layouts.admin>
