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
    $textareaClass = fn (string $field) => 'w-full rounded-md border bg-surface px-3 py-2.5 text-base text-fg placeholder:text-fg-tertiary focus:border-line-focus focus:outline-none focus:ring-4 focus:ring-brand/20 '.($errors->has($field) ? 'border-line-danger' : 'border-line');
    $checkboxCard = 'flex items-center gap-2 rounded-md border border-line px-3 py-2 text-sm text-fg has-checked:border-line-brand has-checked:bg-brand-subtle';
@endphp

<x-layouts.public
    :title="__('Request a Quote')"
    :description="__('Tell us about your project in four short steps. We reply within one business day with next steps.')"
    :alternates="$languages->keys()->mapWithKeys(fn ($code) => [$code => route('quote', ['locale' => $code])])->all()">
    <x-site.page-hero :eyebrow="__('Request a Quote')" :title="__('Tell us about your project')"
                      :lead="__('Tell us about your project in four short steps. We reply within one business day with next steps.')"
                      :crumbs="[__('Request a Quote') => null]" />

    <div class="mx-auto grid max-w-7xl gap-8 px-5 py-14 lg:grid-cols-[minmax(0,9fr)_minmax(0,4fr)] lg:px-20 lg:py-20">
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
                            <li class="flex items-center gap-2 rounded-full border border-line px-3 py-1.5 text-sm text-fg-secondary data-[state=current]:border-line-brand data-[state=current]:bg-brand-subtle data-[state=current]:font-semibold data-[state=current]:text-fg-brand data-[state=done]:text-fg"
                                data-stepper-indicator="{{ $number }}">
                                <span class="flex size-6 items-center justify-center rounded-full bg-brand text-xs font-semibold text-fg-on-brand">{{ $number }}</span>
                                {{ $label }}
                            </li>
                        @endforeach
                    </ol>
                    <p class="sr-only" aria-live="polite" data-stepper-status></p>

                    {{-- Step 1 --}}
                    <fieldset class="flex flex-col gap-5 rounded-2xl border border-line bg-surface p-6 sm:p-8" data-step="1">
                        <legend class="contents">
                            <span class="flex items-center gap-3 text-xl font-bold text-fg">
                                <span class="flex size-7 items-center justify-center rounded-full bg-brand text-sm text-fg-on-brand" aria-hidden="true">1</span>
                                {{ $steps[1] }}
                            </span>
                        </legend>
                        <div class="grid gap-5 sm:grid-cols-2">
                            <x-ui.input name="name" :label="__('Full name').' *'" required maxlength="120" autocomplete="name" :placeholder="__('Your name')" />
                            <x-ui.input name="email" type="email" :label="__('Email').' *'" required maxlength="255" autocomplete="email" dir="ltr" placeholder="you@company.com" />
                            <x-ui.input name="phone" type="tel" :label="__('Phone').' *'" required maxlength="30" autocomplete="tel" dir="ltr" placeholder="+20 …" pattern="\+?[0-9 ()\-]{6,30}" />
                            <x-ui.select name="country" :label="__('Country').' *'" :options="$countries" :placeholder="__('Choose a country')" required autocomplete="country" />
                        </div>
                        <x-ui.input name="company" :label="__('Company name')" maxlength="160" autocomplete="organization" :placeholder="__('Optional')" />
                    </fieldset>

                    {{-- Step 2 --}}
                    <fieldset class="flex flex-col gap-5 rounded-2xl border border-line bg-surface p-6 sm:p-8" data-step="2">
                        <legend class="contents">
                            <span class="flex items-center gap-3 text-xl font-bold text-fg">
                                <span class="flex size-7 items-center justify-center rounded-full bg-brand text-sm text-fg-on-brand" aria-hidden="true">2</span>
                                {{ $steps[2] }}
                            </span>
                        </legend>

                        @if ($services->isNotEmpty())
                            <fieldset class="flex flex-col gap-2" data-required-group data-required-message="{{ __('Choose at least one option.') }}">
                                <legend class="mb-1 text-sm font-semibold text-fg">{{ __('What do you need?') }} *</legend>
                                <div class="grid gap-2 sm:grid-cols-2">
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
                            <legend class="mb-1 text-sm font-semibold text-fg">{{ __('Required platforms') }} *</legend>
                            <div class="flex flex-wrap gap-2">
                                @foreach (\App\Enums\ProjectPlatform::options() as $value => $label)
                                    <label class="{{ $checkboxCard }}">
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
                                <label for="{{ $field }}" class="text-sm font-semibold text-fg">{{ $label }}</label>
                                <textarea id="{{ $field }}" name="{{ $field }}" rows="4" maxlength="2000" @if ($required) required @endif
                                          @if ($field === 'description') minlength="30" @endif
                                          aria-describedby="{{ $field }}-help" @error($field) aria-invalid="true" @enderror
                                          placeholder="{{ $placeholder }}" class="{{ $textareaClass($field) }}">{{ old($field, $default) }}</textarea>
                                @error($field)
                                    <p id="{{ $field }}-help" class="text-sm text-fg-danger">{{ $message }}</p>
                                @else
                                    <p id="{{ $field }}-help" class="text-sm text-fg-secondary">{{ $help }}</p>
                                @enderror
                            </div>
                        @endforeach

                        @if ($technologies->isNotEmpty())
                            <details class="rounded-md border border-line p-4" @if ($oldTechnologies) open @endif>
                                <summary class="cursor-pointer text-sm font-semibold text-fg">{{ __('Preferred technologies') }} <span class="font-normal text-fg-tertiary">({{ __('Optional') }})</span></summary>
                                <div class="mt-3 flex flex-wrap gap-2">
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

                        <x-ui.input name="existing_url" type="url" :label="__('Existing website')" maxlength="500" dir="ltr" placeholder="https://" :helper="__('Optional')" />
                    </fieldset>

                    {{-- Step 3 --}}
                    <fieldset class="flex flex-col gap-5 rounded-2xl border border-line bg-surface p-6 sm:p-8" data-step="3">
                        <legend class="contents">
                            <span class="flex items-center gap-3 text-xl font-bold text-fg">
                                <span class="flex size-7 items-center justify-center rounded-full bg-brand text-sm text-fg-on-brand" aria-hidden="true">3</span>
                                {{ $steps[3] }}
                            </span>
                        </legend>
                        <div class="grid gap-5 sm:grid-cols-2">
                            <x-ui.select name="budget_range" :label="__('Estimated budget')" :options="\App\Enums\BudgetRange::options()" :placeholder="__('Optional')" />
                            <x-ui.select name="timeline" :label="__('Timeline')" :options="\App\Enums\ProjectTimeline::options()" :placeholder="__('Optional')" />
                            <x-ui.input name="deadline" type="date" :label="__('Expected deadline')" :min="now()->addDay()->toDateString()" :helper="__('Optional')" />
                        </div>
                        <p class="text-sm text-fg-secondary">{{ __('Budgets are only a guide. Every estimate we send is preliminary until the scope is confirmed in writing.') }}</p>
                    </fieldset>

                    {{-- Step 4 --}}
                    <fieldset class="flex flex-col gap-5 rounded-2xl border border-line bg-surface p-6 sm:p-8" data-step="4">
                        <legend class="contents">
                            <span class="flex items-center gap-3 text-xl font-bold text-fg">
                                <span class="flex size-7 items-center justify-center rounded-full bg-brand text-sm text-fg-on-brand" aria-hidden="true">4</span>
                                {{ $steps[4] }}
                            </span>
                        </legend>

                        <div class="flex flex-col gap-1.5">
                            <label for="attachments" class="text-sm font-semibold text-fg">{{ __('Attachments') }}</label>
                            <input type="file" id="attachments" name="attachments[]" multiple accept="{{ $acceptedExtensions }}" aria-describedby="attachments-help"
                                   data-max-files="{{ $attachmentRules['max_files'] }}" data-max-files-message="{{ __('You can attach up to :count files.', ['count' => $attachmentRules['max_files']]) }}"
                                   class="w-full rounded-md border border-dashed border-line-strong bg-subtle p-4 text-sm text-fg file:me-3 file:rounded-md file:border-0 file:bg-brand file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-fg-on-brand">
                            @if ($errors->has('attachments') || $errors->has('attachments.*'))
                                <p id="attachments-help" class="text-sm text-fg-danger">{{ $errors->first('attachments') ?: collect($errors->get('attachments.*'))->flatten()->first() }}</p>
                            @else
                                <p id="attachments-help" class="text-sm text-fg-secondary">{{ __('PDF, Word, Excel, PowerPoint, PNG, JPG or ZIP — up to :size MB each, max :count files.', ['size' => $maxMb, 'count' => $attachmentRules['max_files']]) }}</p>
                            @endif
                        </div>

                        <div class="grid gap-5 sm:grid-cols-2">
                            <fieldset class="flex flex-col gap-2">
                                <legend class="mb-1 text-sm font-semibold text-fg">{{ __('Preferred contact method') }} *</legend>
                                @foreach (\App\Enums\ContactMethod::options() as $value => $label)
                                    <label class="flex items-center gap-2 text-sm text-fg">
                                        <input type="radio" name="preferred_contact" value="{{ $value }}" required @checked(old('preferred_contact', 'email') === $value) class="size-4 accent-brand">
                                        {{ $label }}
                                    </label>
                                @endforeach
                                @error('preferred_contact')<p class="text-sm text-fg-danger">{{ $message }}</p>@enderror
                            </fieldset>
                            <fieldset class="flex flex-col gap-2">
                                <legend class="mb-1 text-sm font-semibold text-fg">{{ __('Preferred language') }} *</legend>
                                @foreach ($languages as $code => $language)
                                    <label class="flex items-center gap-2 text-sm text-fg">
                                        <input type="radio" name="preferred_locale" value="{{ $code }}" required @checked(old('preferred_locale', app()->getLocale()) === $code) class="size-4 accent-brand">
                                        <span lang="{{ $code }}">{{ $language['native_name'] }}</span>
                                    </label>
                                @endforeach
                                @error('preferred_locale')<p class="text-sm text-fg-danger">{{ $message }}</p>@enderror
                            </fieldset>
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label class="flex items-start gap-3 text-sm text-fg">
                                <input type="checkbox" name="consent" value="1" required @checked(old('consent')) @error('consent') aria-invalid="true" aria-describedby="consent-error" @enderror class="mt-0.5 size-4 shrink-0 accent-brand">
                                <span>{{ __('I agree to the Privacy Policy and consent to CodeFanz storing my request.') }}</span>
                            </label>
                            @error('consent')
                                <p id="consent-error" class="text-sm text-fg-danger">{{ $message }}</p>
                            @enderror
                        </div>
                    </fieldset>

                    <div class="flex flex-wrap items-center gap-3">
                        <x-ui.button type="button" variant="secondary" hidden data-stepper-back>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" class="rtl:-scale-x-100"><path d="M19 12H5m6 6-6-6 6-6"/></svg>
                            {{ __('Back') }}
                        </x-ui.button>
                        <x-ui.button type="button" hidden data-stepper-next>
                            {{ __('Continue') }}
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" class="rtl:-scale-x-100"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
                        </x-ui.button>
                        <x-ui.button data-stepper-submit>
                            {{ __('Submit request') }}
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" class="rtl:-scale-x-100"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
                        </x-ui.button>
                        <p class="text-xs text-fg-tertiary">{{ __('You’ll get a reference number by email. Protected by rate limiting and spam checks — no CAPTCHA puzzles.') }}</p>
                    </div>
                </form>
            @endif
        </div>

        <aside aria-labelledby="quote-next-title" class="h-fit rounded-2xl bg-[linear-gradient(150deg,var(--color-brand-700),var(--color-brand-900)_60%,#0b0820)] p-6 text-fg-on-brand lg:sticky lg:top-24">
            <h2 id="quote-next-title" class="text-lg font-bold">{{ __('What happens next') }}</h2>
            <ol class="mt-4 flex flex-col gap-4 text-sm">
                @foreach ([
                    __('We review your request within one business day'),
                    __('A short discovery call to clarify scope'),
                    __('You receive a preliminary estimate & milestones'),
                    __('Kick-off once you approve the proposal'),
                ] as $index => $stepText)
                    <li class="flex items-start gap-3">
                        <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-fg-on-brand/15 text-xs font-semibold" aria-hidden="true">{{ $index + 1 }}</span>
                        <span>{{ $stepText }}</span>
                    </li>
                @endforeach
            </ol>
            <p class="mt-5 text-xs text-fg-on-brand/70">{{ __('All estimates are preliminary until the scope is confirmed in writing.') }}</p>
        </aside>
    </div>
</x-layouts.public>
