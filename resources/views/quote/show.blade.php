{{-- Request a Quote (Figma "14 · Request a Quote"): four steps in the browser, one long form without JavaScript. --}}
@php
    use App\Http\Requests\QuoteRequest;

    $languages = \App\Models\Language::active();
    $sent = session('quote_sent');
    $sentReference = session('quote_reference');
    $attachmentRules = config('codefanz.leads.attachments');
    $acceptedExtensions = collect($attachmentRules['extensions'])->map(fn ($extension) => '.'.$extension)->implode(',');
    $maxMb = round($attachmentRules['max_kb'] / 1024, 1);
    $initialStep = 1;
    foreach (QuoteRequest::STEPS as $number => $fields) {
        if (collect($fields)->contains(fn ($field) => $errors->has($field) || $errors->has($field.'.*'))) {
            $initialStep = $number;
            break;
        }
    }
    $steps = [1 => __('About you'), 2 => __('Your project'), 3 => __('Budget & timeline'), 4 => __('Files & preferences')];
    $oldServices = array_map('intval', (array) old('services', $selectedServices));
    $oldPlatforms = (array) old('platforms', []);
    $oldTechnologies = array_map('intval', (array) old('technologies', []));
    $textareaClass = fn (string $field) => 'w-full rounded-md border bg-surface px-3 py-2.5 type-body-md text-fg placeholder:text-fg-tertiary focus:border-line-focus focus:outline-none focus:ring-4 focus:ring-brand/20 '.($errors->has($field) ? 'border-line-danger' : 'border-line-strong');
    $checkboxCard = 'flex items-center gap-2 rounded-md border border-line bg-surface px-3 py-2 type-body-sm text-fg transition-colors hover:border-line-strong has-checked:border-line-brand has-checked:bg-brand-subtle';
    $checkboxInline = 'flex items-center gap-2 type-body-md text-fg';
    $stepCard = 'flex flex-col gap-5 rounded-2xl border border-line bg-surface p-5 shadow-xs sm:p-8';
    $stepTitle = 'flex items-center gap-3 type-h5 text-fg sm:type-h4';
    $stepNumber = 'flex size-7 shrink-0 items-center justify-center rounded-full bg-brand type-label-md text-fg-on-brand';
    $fieldLabel = 'type-label-md text-fg';
@endphp

<x-layouts.public
    :title="__('Request a Quote')"
    :description="__('Tell us about your project in four short steps. We reply within one business day with next steps.')"
    :alternates="$languages->keys()->mapWithKeys(fn ($code) => [$code => route('quote', ['locale' => $code])])->all()">
    <x-site.page-hero :eyebrow="__('Start your project')" :title="__('Tell us what you want to build')"
                      :lead="__('Takes about 5 minutes. You’ll receive a free preliminary estimate — no payment or commitment required.')"
                      :crumbs="[__('Request a Quote') => null]">
        <a href="{{ route('assistant') }}" class="inline-flex w-fit items-center gap-2 rounded-sm type-label-md text-fg-brand hover:underline">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="8" width="16" height="12" rx="2"/><path d="M12 8V4M8 14h.01M16 14h.01M9 18h6"/></svg>
            {{ __('Prefer chatting? Use the AI Project Assistant') }}
            <x-ui.arrow :size="16" />
        </a>
    </x-site.page-hero>

    <x-site.section tone="surface" inner="grid items-start gap-8 lg:grid-cols-[minmax(0,43.25rem)_minmax(0,1fr)] lg:gap-10 xl:grid-cols-[minmax(0,43.25rem)_20rem] xl:justify-between">
        <div class="min-w-0">
            @if ($sent)
                <x-ui.alert tone="success" :title="__('Thank you — your request is on its way.')" data-quote-sent>
                    @if ($sentReference)
                        <p>{{ __('Your reference number is :reference.', ['reference' => $sentReference]) }}</p>
                    @endif
                    <p>{{ __('We sent a confirmation to your email and will reply within one business day.') }}</p>
                </x-ui.alert>
            @else
                @if ($errors->any())
                    <x-ui.alert tone="danger" :title="__('Please fix the highlighted fields')" class="mb-6">
                        {{ $errors->first('form') ?: __('Some fields need attention.') }}
                    </x-ui.alert>
                @endif

                <form method="POST" action="{{ route('quote.store') }}" enctype="multipart/form-data" novalidate
                      class="flex flex-col gap-6" data-stepper data-initial-step="{{ $initialStep }}">
                    @csrf
                    <input type="hidden" name="{{ QuoteRequest::TIMESTAMP }}" value="{{ $formToken }}">
                    <input type="hidden" name="reference" value="{{ old('reference', $reference) }}">
                    @foreach ($tracking as $field => $value)
                        <input type="hidden" name="{{ $field }}" value="{{ old($field, $value) }}">
                    @endforeach
                    {{-- Honeypot: hidden from people and assistive technology; bots tend to fill it. --}}
                    <div class="hidden" aria-hidden="true">
                        <label for="quote-website">{{ __('Leave this field empty') }}</label>
                        <input type="text" id="quote-website" name="{{ QuoteRequest::HONEYPOT }}" tabindex="-1" autocomplete="off">
                    </div>

                    {{-- Progress (shown by the stepper script only). --}}
                    <ol class="hidden flex-wrap gap-2" data-stepper-progress aria-label="{{ __('Progress') }}">
                        @foreach ($steps as $number => $label)
                            <li class="group/step flex items-center gap-2 rounded-full border border-line bg-surface py-1 ps-1 pe-3 type-label-md text-fg-secondary data-[state=current]:border-line-brand data-[state=current]:bg-brand-subtle data-[state=current]:text-fg-brand data-[state=done]:text-fg"
                                data-stepper-indicator="{{ $number }}">
                                <span class="flex size-6 items-center justify-center rounded-full bg-muted type-label-sm text-fg-secondary group-data-[state=current]/step:bg-brand group-data-[state=current]/step:text-fg-on-brand group-data-[state=done]/step:bg-accent group-data-[state=done]/step:text-ink-900">{{ $number }}</span>
                                <span class="max-sm:sr-only">{{ $label }}</span>
                            </li>
                        @endforeach
                    </ol>
                    <p class="sr-only" aria-live="polite" data-stepper-status></p>

                    {{-- Step 1 --}}
                    <fieldset class="{{ $stepCard }}" data-step="1">
                        <legend class="contents">
                            <span class="{{ $stepTitle }}">
                                <span class="{{ $stepNumber }}" aria-hidden="true">1</span>
                                {{ $steps[1] }}
                            </span>
                        </legend>
                        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                            <x-ui.input name="name" size="lg" icon="user" :label="__('Full name').' *'" required maxlength="120" autocomplete="name" :placeholder="__('Your name')" />
                            <x-ui.input name="email" size="lg" icon="mail" type="email" :label="__('Email').' *'" required maxlength="255" autocomplete="email" dir="ltr" placeholder="you@company.com" />
                            <x-ui.input name="phone" size="lg" icon="phone" type="tel" :label="__('Phone').' *'" required maxlength="30" autocomplete="tel" dir="ltr" placeholder="+20 …" pattern="\+?[0-9 ()\-]{6,30}" />
                            <x-ui.select name="country" size="lg" :label="__('Country').' *'" :options="$countries" :placeholder="__('Choose a country')" required autocomplete="country" />
                        </div>
                        <x-ui.input name="company" size="lg" :label="__('Company name')" maxlength="160" autocomplete="organization" :placeholder="__('Optional')" />
                    </fieldset>

                    {{-- Step 2 --}}
                    <fieldset class="{{ $stepCard }}" data-step="2">
                        <legend class="contents">
                            <span class="{{ $stepTitle }}">
                                <span class="{{ $stepNumber }}" aria-hidden="true">2</span>
                                {{ $steps[2] }}
                            </span>
                        </legend>

                        @if ($services->isNotEmpty())
                            <fieldset class="flex flex-col gap-2" data-required-group data-required-message="{{ __('Choose at least one option.') }}">
                                <legend class="mb-2 {{ $fieldLabel }}">{{ __('What do you need?') }} *</legend>
                                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                    @foreach ($services as $service)
                                        <label class="{{ $checkboxCard }}">
                                            <input type="checkbox" name="services[]" value="{{ $service->id }}" @checked(in_array($service->id, $oldServices, true)) class="size-4 accent-brand">
                                            <span dir="auto">{{ $service->translation()?->title }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                @error('services')<p class="text-sm text-fg-danger">{{ $message }}</p>@enderror
                                @error('services.*')<p class="text-sm text-fg-danger">{{ $message }}</p>@enderror
                            </fieldset>
                        @endif

                        <fieldset class="flex flex-col gap-2" data-required-group data-required-message="{{ __('Choose at least one option.') }}">
                            <legend class="mb-2 {{ $fieldLabel }}">{{ __('Required platforms') }} *</legend>
                            <div class="flex flex-wrap gap-x-6 gap-y-2">
                                @foreach (\App\Enums\ProjectPlatform::options() as $value => $label)
                                    <label class="{{ $checkboxInline }}">
                                        <input type="checkbox" name="platforms[]" value="{{ $value }}" @checked(in_array($value, $oldPlatforms, true)) class="size-4 accent-brand">
                                        {{ $label }}
                                    </label>
                                @endforeach
                            </div>
                            @error('platforms')<p class="text-sm text-fg-danger">{{ $message }}</p>@enderror
                        </fieldset>

                        @foreach ([
                            'description' => [__('Project description').' *', __('What problem does it solve, and for whom?'), __('At least 30 characters. Please don’t share passwords or payment details.'), true, $reference !== '' ? $reference.":\n\n" : ''],
                            'features' => [__('Main features').' *', __('e.g. booking, payments, admin dashboard, reports…'), __('One per line works best.'), true, ''],
                            'goals' => [__('Business goals'), __('What should this project achieve? e.g. fewer phone bookings, new revenue…'), __('Optional'), false, ''],
                        ] as $field => [$label, $placeholder, $help, $required, $default])
                            <div class="flex flex-col gap-1.5">
                                <label for="{{ $field }}" class="{{ $fieldLabel }}">{{ $label }}</label>
                                <textarea id="{{ $field }}" name="{{ $field }}" rows="4" maxlength="2000" @if ($required) required @endif
                                          @if ($field === 'description') minlength="30" @endif
                                          aria-describedby="{{ $field }}-help" @error($field) aria-invalid="true" @enderror
                                          data-char-count="2000" placeholder="{{ $placeholder }}" class="{{ $textareaClass($field) }}">{{ old($field, $default) }}</textarea>
                                <div class="flex items-start justify-between gap-4">
                                    @error($field)
                                        <p id="{{ $field }}-help" class="text-sm text-fg-danger">{{ $message }}</p>
                                    @else
                                        <p id="{{ $field }}-help" class="type-body-xs text-fg-secondary">{{ $help }}</p>
                                    @enderror
                                    <span class="shrink-0 type-body-xs text-fg-tertiary tabular-nums" dir="ltr" data-char-count-output data-char-count-quiet aria-hidden="true"></span>
                                </div>
                            </div>
                        @endforeach

                        @if ($technologies->isNotEmpty())
                            <details class="group rounded-md border border-line bg-surface open:border-line-brand" @if ($oldTechnologies) open @endif>
                                <summary class="flex h-12 cursor-pointer list-none items-center gap-3 rounded-md px-3 type-body-md text-fg [&::-webkit-details-marker]:hidden">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="shrink-0 text-icon-secondary"><path d="m8 8-4 4 4 4M16 8l4 4-4 4"/></svg>
                                    <span class="flex-1">{{ __('Preferred technologies') }} <span class="text-fg-tertiary">({{ __('Optional') }})</span></span>
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="shrink-0 text-icon-secondary transition-transform group-open:rotate-180"><path d="m6 9 6 6 6-6"/></svg>
                                </summary>
                                <div class="flex flex-wrap gap-2 px-3 pb-3">
                                    @foreach ($technologies as $technology)
                                        <label class="{{ $checkboxCard }}">
                                            <input type="checkbox" name="technologies[]" value="{{ $technology->id }}" @checked(in_array($technology->id, $oldTechnologies, true)) class="size-4 accent-brand">
                                            <span dir="auto">{{ $technology->translation()?->name }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                @error('technologies.*')<p class="mt-2 text-sm text-fg-danger">{{ $message }}</p>@enderror
                            </details>
                        @endif

                        <x-ui.input name="existing_url" size="lg" icon="globe" type="url" autocomplete="url" :label="__('Existing website')" maxlength="500" dir="ltr" placeholder="https://" :helper="__('Optional')" />
                    </fieldset>

                    {{-- Step 3 --}}
                    <fieldset class="{{ $stepCard }}" data-step="3">
                        <legend class="contents">
                            <span class="{{ $stepTitle }}">
                                <span class="{{ $stepNumber }}" aria-hidden="true">3</span>
                                {{ $steps[3] }}
                            </span>
                        </legend>
                        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                            <x-ui.select name="budget_range" size="lg" :label="__('Estimated budget')" :options="\App\Enums\BudgetRange::options()" :placeholder="__('Optional')" />
                            <x-ui.select name="timeline" size="lg" :label="__('Timeline')" :options="\App\Enums\ProjectTimeline::options()" :placeholder="__('Optional')" />
                            <x-ui.input name="deadline" size="lg" icon="calendar" type="date" :label="__('Expected deadline')" :min="now()->addDay()->toDateString()" :helper="__('Optional')" />
                        </div>
                        <p class="type-body-sm text-fg-secondary">{{ __('Budgets are only a guide. Every estimate we send is preliminary until the scope is confirmed in writing.') }}</p>
                    </fieldset>

                    {{-- Step 4 --}}
                    <fieldset class="{{ $stepCard }}" data-step="4">
                        <legend class="contents">
                            <span class="{{ $stepTitle }}">
                                <span class="{{ $stepNumber }}" aria-hidden="true">4</span>
                                {{ $steps[4] }}
                            </span>
                        </legend>

                        <div class="flex flex-col gap-1.5">
                            <span class="{{ $fieldLabel }}" id="attachments-label">{{ __('Attachments') }}</span>
                            <div class="relative flex flex-col items-center gap-2 rounded-lg border border-dashed border-line-strong bg-subtle px-4 py-6 text-center transition-colors focus-within:border-line-focus focus-within:ring-4 focus-within:ring-brand/20 hover:border-line-brand data-dragging:border-line-brand data-dragging:bg-brand-subtle" data-dropzone>
                                <span class="flex size-10 items-center justify-center rounded-full bg-surface text-icon-brand shadow-xs" aria-hidden="true">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M12 15V4m-4 4 4-4 4 4"/><path d="M4 15v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"/></svg>
                                </span>
                                <p class="type-body-sm text-fg-secondary" aria-hidden="true"><span class="type-label-md text-fg-brand">{{ __('Click to upload') }}</span> {{ __('or drag and drop') }}</p>
                                <input type="file" id="attachments" name="attachments[]" multiple accept="{{ $acceptedExtensions }}" aria-labelledby="attachments-label" aria-describedby="attachments-help"
                                       data-max-files="{{ $attachmentRules['max_files'] }}" data-max-files-message="{{ __('You can attach up to :count files.', ['count' => $attachmentRules['max_files']]) }}" data-file-list="attachments-list"
                                       class="absolute inset-0 size-full cursor-pointer opacity-0">
                                @if ($errors->has('attachments') || $errors->has('attachments.*'))
                                    <p id="attachments-help" class="type-body-xs text-fg-danger">{{ $errors->first('attachments') ?: collect($errors->get('attachments.*'))->flatten()->first() }}</p>
                                @else
                                    <p id="attachments-help" class="type-body-xs text-fg-tertiary">{{ __('PDF, Word, Excel, PowerPoint, PNG, JPG or ZIP — up to :size MB each, max :count files.', ['size' => $maxMb, 'count' => $attachmentRules['max_files']]) }}</p>
                                @endif
                                <ul id="attachments-list" hidden class="relative z-10 flex flex-wrap justify-center gap-2 type-label-sm text-fg [&>li]:rounded-full [&>li]:bg-surface [&>li]:px-3 [&>li]:py-1 [&>li]:shadow-xs" aria-live="polite"></ul>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                            <fieldset class="flex flex-col gap-2">
                                <legend class="mb-2 {{ $fieldLabel }}">{{ __('Preferred contact method') }} *</legend>
                                @foreach (\App\Enums\ContactMethod::options() as $value => $label)
                                    <label class="{{ $checkboxInline }}">
                                        <input type="radio" name="preferred_contact" value="{{ $value }}" required @checked(old('preferred_contact', 'email') === $value) class="size-4 accent-brand">
                                        {{ $label }}
                                    </label>
                                @endforeach
                                @error('preferred_contact')<p class="text-sm text-fg-danger">{{ $message }}</p>@enderror
                            </fieldset>
                            <fieldset class="flex flex-col gap-2">
                                <legend class="mb-2 {{ $fieldLabel }}">{{ __('Preferred language') }} *</legend>
                                @foreach ($languages as $code => $language)
                                    <label class="{{ $checkboxInline }}">
                                        <input type="radio" name="preferred_locale" value="{{ $code }}" required @checked(old('preferred_locale', app()->getLocale()) === $code) class="size-4 accent-brand">
                                        <span lang="{{ $code }}">{{ $language['native_name'] }}</span>
                                    </label>
                                @endforeach
                                @error('preferred_locale')<p class="text-sm text-fg-danger">{{ $message }}</p>@enderror
                            </fieldset>
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label class="flex items-start gap-3 type-body-md text-fg">
                                <input type="checkbox" name="consent" value="1" required @checked(old('consent')) @error('consent') aria-invalid="true" aria-describedby="consent-error" @enderror class="mt-1 size-4 shrink-0 accent-brand">
                                <span>{{ __('I agree to the Privacy Policy and consent to CodeFanz storing my request.') }}</span>
                            </label>
                            @error('consent')
                                <p id="consent-error" class="text-sm text-fg-danger">{{ $message }}</p>
                            @enderror
                        </div>
                    </fieldset>

                    <div class="flex flex-wrap items-center gap-3 sm:gap-4">
                        <x-ui.button type="button" variant="secondary" size="lg" hidden data-stepper-back>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="shrink-0 rtl:-scale-x-100"><path d="M19 12H5m6 6-6-6 6-6"/></svg>
                            {{ __('Back') }}
                        </x-ui.button>
                        <x-ui.button type="button" size="lg" hidden data-stepper-next arrow>{{ __('Continue') }}</x-ui.button>
                        <x-ui.button size="lg" data-stepper-submit arrow>{{ __('Submit request') }}</x-ui.button>
                        <p class="type-body-xs text-fg-tertiary">{{ __('You’ll get a reference number by email. Protected by rate limiting and spam checks — no CAPTCHA puzzles.') }}</p>
                    </div>
                </form>
            @endif
        </div>

        <aside aria-labelledby="quote-next-title" class="dark h-fit rounded-2xl bg-[linear-gradient(150deg,var(--color-brand-700),var(--color-brand-900)_55%,var(--color-ink-900))] p-6 text-fg shadow-lg lg:sticky lg:top-24">
            <h2 id="quote-next-title" class="type-h5 text-fg">{{ __('What happens next') }}</h2>
            <ol class="mt-5 flex flex-col gap-4 type-body-sm text-fg">
                @foreach ([
                    __('We review your request within one business day'),
                    __('A short discovery call to clarify scope'),
                    __('You receive a preliminary estimate & milestones'),
                    __('Kick-off once you approve the proposal'),
                ] as $index => $stepText)
                    <li class="flex items-start gap-3">
                        <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-accent type-label-sm text-ink-900" aria-hidden="true">{{ $index + 1 }}</span>
                        <span>{{ $stepText }}</span>
                    </li>
                @endforeach
            </ol>
            <p class="mt-5 type-body-xs text-fg-secondary">{{ __('All estimates are preliminary until the scope is confirmed in writing.') }}</p>
        </aside>
    </x-site.section>
</x-layouts.public>
