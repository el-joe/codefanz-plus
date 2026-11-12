@php
    $languages = \App\Models\Language::active()->map(fn ($language) => $language['native_name']);
    $selectedServices = array_map('intval', (array) old('services', $lead->exists ? $lead->services->pluck('id')->all() : []));
    $selectedTechnologies = array_map('intval', (array) old('technologies', $lead->exists ? $lead->technologies->pluck('id')->all() : []));
    $selectedPlatforms = (array) old('platforms', $lead->platforms ?? []);
    $estimatedValue = $lead->estimated_value_minor !== null ? $lead->estimated_value_minor / 100 : null;
    $textarea = fn (string $field) => 'w-full rounded-md border bg-surface px-3 py-2.5 text-base text-fg focus:border-line-focus focus:outline-none focus:ring-4 focus:ring-brand/20 '.($errors->has($field) ? 'border-line-danger' : 'border-line');
    $chip = 'flex items-center gap-2 rounded-md border border-line px-3 py-1.5 text-sm text-fg has-checked:border-line-brand has-checked:bg-brand-subtle';
@endphp

<x-layouts.admin :title="$lead->exists ? __('Edit :reference', ['reference' => $lead->reference]) : __('Add lead')" :crumb="__('Leads')">
    @if ($errors->any())
        <x-ui.alert tone="danger" :title="__('Please fix the highlighted fields')">{{ $errors->first('lock_version') ?: __('Some fields need attention.') }}</x-ui.alert>
    @endif

    <form method="POST" action="{{ $lead->exists ? route('admin.leads.update', $lead) : route('admin.leads.store') }}" class="grid gap-6 xl:grid-cols-[1fr_340px]">
        @csrf
        @if ($lead->exists)
            @method('PUT')
            <input type="hidden" name="lock_version" value="{{ old('lock_version', $lead->lock_version) }}">
        @endif

        <div class="flex min-w-0 flex-col gap-6">
            <x-ui.card :title="__('Contact')">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input name="name" :label="__('Full name')" :value="$lead->name" required maxlength="120" dir="auto" />
                    <x-ui.input name="email" type="email" :label="__('Email')" :value="$lead->email" required maxlength="255" dir="ltr" />
                    <x-ui.input name="phone" type="tel" :label="__('Phone')" :value="$lead->phone" maxlength="30" dir="ltr" />
                    <x-ui.input name="company" :label="__('Company name')" :value="$lead->company" maxlength="160" dir="auto" />
                    <x-ui.select name="country" :label="__('Country')" :options="$countries" :value="$lead->country" :placeholder="__('Unknown')" />
                    <x-ui.select name="preferred_contact" :label="__('Preferred contact method')" :options="\App\Enums\ContactMethod::options()" :value="$lead->preferred_contact?->value ?? 'email'" />
                    <x-ui.select name="preferred_locale" :label="__('Preferred language')" :options="$languages" :value="$lead->preferred_locale" />
                </div>
            </x-ui.card>

            <x-ui.card :title="__('Project requirements')">
                <div class="flex flex-col gap-5">
                    @if ($services->isNotEmpty())
                        <fieldset>
                            <legend class="mb-2 text-sm font-semibold text-fg">{{ __('Services') }}</legend>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($services as $service)
                                    <label class="{{ $chip }}">
                                        <input type="checkbox" name="services[]" value="{{ $service->id }}" @checked(in_array($service->id, $selectedServices, true)) class="size-4 accent-brand">
                                        <span dir="auto">{{ $service->translation()?->title ?? '#'.$service->id }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    @endif
                    <fieldset>
                        <legend class="mb-2 text-sm font-semibold text-fg">{{ __('Required platforms') }}</legend>
                        <div class="flex flex-wrap gap-2">
                            @foreach (\App\Enums\ProjectPlatform::options() as $value => $label)
                                <label class="{{ $chip }}">
                                    <input type="checkbox" name="platforms[]" value="{{ $value }}" @checked(in_array($value, $selectedPlatforms, true)) class="size-4 accent-brand">
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                    @foreach (['description' => __('Project description'), 'features' => __('Main features'), 'goals' => __('Business goals')] as $field => $label)
                        <div class="flex flex-col gap-1.5">
                            <label for="{{ $field }}" class="text-sm font-semibold text-fg">{{ $label }}</label>
                            <textarea id="{{ $field }}" name="{{ $field }}" rows="4" maxlength="5000" dir="auto" class="{{ $textarea($field) }}">{{ old($field, $lead->{$field}) }}</textarea>
                            @error($field)<p class="text-sm text-fg-danger">{{ $message }}</p>@enderror
                        </div>
                    @endforeach
                    @if ($technologies->isNotEmpty())
                        <details class="rounded-md border border-line p-4" @if ($selectedTechnologies) open @endif>
                            <summary class="cursor-pointer text-sm font-semibold text-fg">{{ __('Preferred technologies') }}</summary>
                            <div class="mt-3 flex flex-wrap gap-2">
                                @foreach ($technologies as $technology)
                                    <label class="{{ $chip }}">
                                        <input type="checkbox" name="technologies[]" value="{{ $technology->id }}" @checked(in_array($technology->id, $selectedTechnologies, true)) class="size-4 accent-brand">
                                        <span dir="auto">{{ $technology->translation()?->name ?? $technology->key }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </details>
                    @endif
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.input name="existing_url" type="url" :label="__('Existing website')" :value="$lead->existing_url" maxlength="500" dir="ltr" placeholder="https://" />
                        <x-ui.select name="budget_range" :label="__('Estimated budget')" :options="\App\Enums\BudgetRange::options()" :value="$lead->budget_range?->value" :placeholder="__('Not specified')" />
                        <x-ui.select name="timeline" :label="__('Timeline')" :options="\App\Enums\ProjectTimeline::options()" :value="$lead->timeline?->value" :placeholder="__('Not specified')" />
                        <x-ui.input name="deadline" type="date" :label="__('Expected deadline')" :value="$lead->deadline?->toDateString()" />
                    </div>
                </div>
            </x-ui.card>
        </div>

        <aside class="flex flex-col gap-6">
            <x-ui.card :title="__('Sales')">
                <div class="flex flex-col gap-4">
                    <x-ui.select name="priority" :label="__('Priority')" :options="\App\Enums\LeadPriority::options()" :value="$lead->priority?->value ?? 'normal'" />
                    @unless ($lead->exists)
                        <x-ui.select name="assigned_to" :label="__('Assigned to')" :options="$assignees" :value="auth()->id()" :placeholder="__('Unassigned')" />
                    @endunless
                    <x-ui.input name="follow_up_at" type="datetime-local" :label="__('Follow-up')" :value="$lead->follow_up_at?->format('Y-m-d\TH:i')" />
                    <div class="grid grid-cols-[1fr_96px] gap-2">
                        <x-ui.input name="estimated_value" type="number" min="0" step="0.01" :label="__('Estimated value')" :value="$estimatedValue" dir="ltr" />
                        <x-ui.input name="currency" :label="__('Currency')" :value="$lead->currency ?? config('codefanz.leads.default_currency')" maxlength="3" dir="ltr" />
                    </div>
                    <x-ui.button class="w-full">{{ $lead->exists ? __('Save changes') : __('Create lead') }}</x-ui.button>
                    @if ($lead->exists)
                        <a href="{{ route('admin.leads.show', $lead) }}" class="text-center text-sm font-semibold text-fg-brand hover:underline">{{ __('Cancel') }}</a>
                    @endif
                </div>
            </x-ui.card>
        </aside>
    </form>
</x-layouts.admin>
