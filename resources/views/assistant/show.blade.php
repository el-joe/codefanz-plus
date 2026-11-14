{{-- AI Project Assistant (Figma "15 · AI Project Assistant"). The chat is driven by resources/js/modules/assistant.js;
     model output is always rendered with textContent. When AI is unavailable the same page runs a guided questionnaire. --}}
@php
    use App\Http\Requests\Assistant\StartAssistantRequest;

    $languages = \App\Models\Language::active();
    $field = 'w-full rounded-md border border-line bg-surface px-3 py-2.5 text-base text-fg placeholder:text-fg-tertiary focus:border-line-focus focus:outline-none focus:ring-4 focus:ring-brand/20';
    $choice = 'flex items-center gap-2 rounded-md border border-line px-3 py-2 text-sm text-fg has-checked:border-line-brand has-checked:bg-brand-subtle';
    $botIcon = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="8" width="16" height="12" rx="2"/><path d="M12 8V4M8 14h.01M16 14h.01M9 18h6"/></svg>';
    $initialQuickReplies = [__('A website'), __('A mobile app'), __('An online store'), __('Something else')];
@endphp

<x-layouts.public
    :title="__('AI Project Assistant')"
    :description="__('Describe your idea to our free AI project assistant and get a structured project brief to our team — no payment needed.')"
    :alternates="$languages->keys()->mapWithKeys(fn ($code) => [$code => route('assistant', ['locale' => $code])])->all()">
    <div class="bg-canvas">
        <div class="container-page grid grid-cols-1 gap-6 py-4 max-sm:px-3 sm:py-6 lg:grid-cols-[minmax(0,1fr)_20.625rem] lg:py-8"
             data-assistant
             data-mode="{{ $aiAvailable ? 'ai' : 'guided' }}"
             data-start-url="{{ route('assistant.start') }}"
             data-message-url="{{ route('assistant.message', ['chatSession' => '__SESSION__']) }}"
             data-answer-url="{{ route('assistant.answer', ['chatSession' => '__SESSION__']) }}"
             data-confirm-url="{{ route('assistant.confirm', ['chatSession' => '__SESSION__']) }}"
             data-form-token="{{ $formToken }}"
             data-initial-quick='@json($aiAvailable ? $initialQuickReplies : [])'
             data-error-text="{{ __('Something went wrong. Please try again.') }}"
             data-rate-text="{{ __('You are sending messages too quickly. Please wait a moment.') }}"
             data-thinking-text="{{ __('The assistant is typing…') }}"
             data-progress-text="{{ __(':collected of :total details collected') }}"
             data-strip-text="{{ __('Brief: :collected of :total details collected') }}">

            <section class="flex min-w-0 flex-col overflow-hidden rounded-3xl border border-v2-hairline bg-surface shadow-v2-card" aria-labelledby="assistant-title">
                <header class="flex items-center gap-3 border-b border-line px-4 py-3 sm:px-6 sm:py-4">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-brand text-fg-on-brand sm:size-11">{!! $botIcon !!}</span>
                    <div class="min-w-0 flex-1">
                        <h1 id="assistant-title" class="truncate type-label-lg text-fg">{{ __('AI Project Assistant') }}</h1>
                        <p class="flex items-center gap-1.5 truncate type-body-xs text-fg-tertiary">
                            <span class="size-2 shrink-0 rounded-full bg-success-600" aria-hidden="true"></span>
                            <span data-assistant-status data-ai-text="{{ __('Online · replies instantly') }}" data-guided-text="{{ __('Guided questionnaire') }}">{{ $aiAvailable ? __('Online · replies instantly') : __('Guided questionnaire') }}</span>
                        </p>
                    </div>
                    <x-ui.badge tone="success" class="shrink-0 max-sm:hidden" data-assistant-free>{{ __('Free · no payment needed') }}</x-ui.badge>
                </header>

                {{-- Mobile: collapsible brief strip. --}}
                <details class="group border-b border-line bg-brand-subtle lg:hidden" data-assistant-strip>
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-2.5 text-sm font-semibold text-fg-brand">
                        <span data-progress-strip>{{ __('Brief: :collected of :total details collected', ['collected' => $progress['collected'], 'total' => $progress['total']]) }}</span>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" class="transition-transform group-open:rotate-180"><path d="m6 9 6 6 6-6"/></svg>
                    </summary>
                    <ul class="flex flex-col gap-2 px-4 pb-3 text-sm" data-progress-list></ul>
                </details>

                <div class="hidden border-b border-line-warning bg-warning-subtle px-4 py-3 text-sm text-fg sm:px-5" role="status" data-assistant-notice
                     @unless ($aiAvailable) data-initial-notice="{{ \App\Services\Assistant\ProjectAssistant::fallbackNotice(null) }}" @endunless></div>

                <div class="flex h-[calc(100dvh-20rem)] min-h-96 flex-col gap-4 overflow-y-auto bg-surface px-4 py-5 sm:px-6 lg:h-[36rem]" data-assistant-scroll>
                    <div role="log" aria-live="polite" aria-label="{{ __('Conversation') }}">
                        <ol class="flex flex-col gap-4" data-assistant-log>
                            <li class="flex items-end gap-2" data-assistant-intro>
                                <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-brand text-fg-on-brand">{!! $botIcon !!}</span>
                                <div class="max-w-[85%] rounded-xl rounded-es-sm bg-subtle px-3 py-2.5 type-body-sm text-fg sm:max-w-[60%] sm:type-body-md">
                                    {{ __("Hi! I'm the CodeFanz project assistant. I'll ask a few questions about your idea and prepare a project brief for our team. It's free — no payment needed.") }}
                                </div>
                            </li>
                            <li class="flex items-end gap-2" data-assistant-intro>
                                <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-brand text-fg-on-brand">{!! $botIcon !!}</span>
                                <div class="max-w-[85%] rounded-xl rounded-es-sm bg-subtle px-3 py-2.5 type-body-sm text-fg sm:max-w-[60%] sm:type-body-md">
                                    {{ $aiAvailable ? __('What would you like to build?') : __('What would you like to build? Give it a short name and tell us about your business.') }}
                                </div>
                            </li>
                        </ol>
                    </div>

                    <p class="hidden text-xs text-fg-tertiary" data-assistant-typing aria-hidden="true">{{ __('The assistant is typing…') }}</p>

                    {{-- Confirm-summary card (filled by the script with textContent). --}}
                    <section class="hidden max-w-xl rounded-xl border border-line-brand bg-surface p-5 shadow-xs" data-assistant-summary aria-labelledby="summary-title">
                        <h2 id="summary-title" class="type-label-md text-fg">{{ __('Here’s what I understood — please confirm:') }}</h2>
                        <dl class="mt-3 flex flex-col gap-2 type-body-sm" data-summary-rows></dl>

                        <form class="mt-4 flex flex-col gap-3 border-t border-line-subtle pt-4" data-assistant-confirm novalidate>
                            <p class="text-sm font-semibold text-fg">{{ __('Your contact details') }}</p>
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <label class="flex flex-col gap-1 text-sm text-fg">{{ __('Full name') }} *
                                    <input name="name" required maxlength="120" autocomplete="name" class="{{ $field }}">
                                </label>
                                <label class="flex flex-col gap-1 text-sm text-fg">{{ __('Email') }} *
                                    <input name="email" type="email" required maxlength="255" autocomplete="email" dir="ltr" class="{{ $field }}">
                                </label>
                                <label class="flex flex-col gap-1 text-sm text-fg">{{ __('Phone') }}
                                    <input name="phone" type="tel" maxlength="30" autocomplete="tel" dir="ltr" class="{{ $field }}">
                                </label>
                                <label class="flex flex-col gap-1 text-sm text-fg">{{ __('Company name') }}
                                    <input name="company" maxlength="160" autocomplete="organization" class="{{ $field }}">
                                </label>
                                <label class="flex flex-col gap-1 text-sm text-fg">{{ __('Preferred contact method') }} *
                                    <select name="preferred_contact" class="{{ $field }}">
                                        @foreach (\App\Enums\ContactMethod::options() as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="flex flex-col gap-1 text-sm text-fg">{{ __('Preferred language') }} *
                                    <select name="preferred_locale" class="{{ $field }}">
                                        @foreach ($languages as $code => $language)
                                            <option value="{{ $code }}" lang="{{ $code }}" @selected($code === app()->getLocale())>{{ $language['native_name'] }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            </div>
                            <label class="flex items-start gap-3 text-sm text-fg">
                                <input type="checkbox" name="consent" value="1" required class="mt-0.5 size-4 shrink-0 accent-brand">
                                <span>{{ __('I agree to the Privacy Policy and consent to CodeFanz storing this conversation and my brief.') }}</span>
                            </label>
                            <p class="hidden text-sm text-fg-danger" role="alert" data-confirm-error></p>
                            <div class="flex flex-wrap items-center gap-2">
                                <x-ui.button size="sm" data-confirm-submit>
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="m5 12 5 5L20 7"/></svg>
                                    {{ __('Confirm & send') }}
                                </x-ui.button>
                                <x-ui.button type="button" size="sm" variant="secondary" data-summary-edit>{{ __('Edit') }}</x-ui.button>
                            </div>
                            <p class="text-xs text-fg-tertiary">{{ __('Any estimate I share is preliminary and not a binding quote.') }}</p>
                        </form>
                    </section>

                    <x-ui.alert tone="success" :title="__('Thank you — your brief is on its way.')" class="hidden" data-assistant-done>
                        <p data-done-text></p>
                    </x-ui.alert>
                </div>

                <div class="flex flex-wrap gap-2 px-4 pb-2 sm:px-5" data-assistant-quick role="group" aria-label="{{ __('Suggested replies') }}"></div>

                {{-- Guided questionnaire (fallback when AI is unavailable): one step at a time. --}}
                <div class="hidden border-t border-line bg-subtle px-4 py-4 sm:px-5" data-assistant-guided>
                    <form class="hidden flex-col gap-3" data-guided-step="project" novalidate>
                        <label class="flex flex-col gap-1 text-sm font-semibold text-fg">{{ __('Project name') }}
                            <input name="project_type" required maxlength="120" placeholder="{{ __('e.g. Clinic booking platform') }}" class="{{ $field }} font-normal">
                        </label>
                        <label class="flex flex-col gap-1 text-sm font-semibold text-fg">{{ __('Project description') }}
                            <textarea name="description" rows="3" required minlength="10" maxlength="2000" class="{{ $field }} font-normal"></textarea>
                        </label>
                        @if ($services->isNotEmpty())
                            <fieldset class="flex flex-col gap-2">
                                <legend class="mb-1 text-sm font-semibold text-fg">{{ __('Services (optional)') }}</legend>
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($services as $service)
                                        <label class="{{ $choice }}"><input type="checkbox" name="services[]" value="{{ $service->id }}" class="size-4 accent-brand">{{ $service->translation()?->title }}</label>
                                    @endforeach
                                </div>
                            </fieldset>
                        @endif
                        <x-ui.button size="sm" class="w-fit">{{ __('Continue') }}</x-ui.button>
                    </form>
                    <form class="hidden flex-col gap-3" data-guided-step="platforms" novalidate>
                        <fieldset class="flex flex-col gap-2">
                            <legend class="mb-1 text-sm font-semibold text-fg">{{ __('Platforms') }}</legend>
                            <div class="flex flex-wrap gap-2">
                                @foreach (\App\Enums\ProjectPlatform::options() as $value => $label)
                                    <label class="{{ $choice }}"><input type="checkbox" name="platforms[]" value="{{ $value }}" class="size-4 accent-brand">{{ $label }}</label>
                                @endforeach
                            </div>
                        </fieldset>
                        <x-ui.button size="sm" class="w-fit">{{ __('Continue') }}</x-ui.button>
                    </form>
                    <form class="hidden flex-col gap-3" data-guided-step="features" novalidate>
                        <label class="flex flex-col gap-1 text-sm font-semibold text-fg">{{ __('Key features (one per line)') }}
                            <textarea name="features" rows="4" required maxlength="2000" class="{{ $field }} font-normal"></textarea>
                        </label>
                        <x-ui.button size="sm" class="w-fit">{{ __('Continue') }}</x-ui.button>
                    </form>
                    <form class="hidden flex-col gap-3" data-guided-step="timeline" novalidate>
                        <fieldset class="flex flex-col gap-2">
                            <legend class="mb-1 text-sm font-semibold text-fg">{{ __('Timeline') }}</legend>
                            <div class="flex flex-wrap gap-2">
                                @foreach (\App\Enums\ProjectTimeline::options() as $value => $label)
                                    <label class="{{ $choice }}"><input type="radio" name="timeline" value="{{ $value }}" required class="size-4 accent-brand">{{ $label }}</label>
                                @endforeach
                            </div>
                        </fieldset>
                        <x-ui.button size="sm" class="w-fit">{{ __('Continue') }}</x-ui.button>
                    </form>
                    <form class="hidden flex-col gap-3" data-guided-step="budget" novalidate>
                        <fieldset class="flex flex-col gap-2">
                            <legend class="mb-1 text-sm font-semibold text-fg">{{ __('Estimated budget') }}</legend>
                            <div class="flex flex-wrap gap-2">
                                @foreach (\App\Enums\BudgetRange::options() as $value => $label)
                                    <label class="{{ $choice }}"><input type="radio" name="budget_range" value="{{ $value }}" class="size-4 accent-brand">{{ $label }}</label>
                                @endforeach
                            </div>
                        </fieldset>
                        <div class="flex gap-2">
                            <x-ui.button size="sm">{{ __('Continue') }}</x-ui.button>
                            <x-ui.button type="button" size="sm" variant="secondary" data-guided-skip>{{ __('Skip') }}</x-ui.button>
                        </div>
                    </form>
                    <p class="mt-2 hidden text-sm text-fg-danger" role="alert" data-guided-error></p>
                </div>

                <form class="border-t border-line px-4 py-3 sm:px-6 sm:py-4" data-assistant-composer>
                    {{-- Honeypot: hidden from people and assistive technology; bots tend to fill it. --}}
                    <div class="hidden" aria-hidden="true">
                        <label for="assistant-website">{{ __('Leave this field empty') }}</label>
                        <input type="text" id="assistant-website" name="{{ StartAssistantRequest::HONEYPOT }}" tabindex="-1" autocomplete="off">
                    </div>
                    <div class="flex items-end gap-2 rounded-xl border border-line-strong bg-subtle p-2 focus-within:border-line-focus focus-within:ring-4 focus-within:ring-brand/20">
                        <label for="assistant-message" class="sr-only">{{ __('Your message') }}</label>
                        <textarea id="assistant-message" name="message" rows="1" maxlength="{{ config('codefanz.assistant.max_message_length') }}" required dir="auto"
                                  placeholder="{{ __('Type your message…') }}"
                                  class="max-h-40 min-h-10 flex-1 resize-none bg-transparent px-2 py-2 type-body-md text-fg placeholder:text-fg-tertiary focus:outline-none"></textarea>
                        <button type="submit" class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-brand text-fg-on-brand hover:bg-brand-hover disabled:bg-disabled disabled:text-fg-disabled" data-composer-send>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" class="rtl:-scale-x-100"><path d="m22 2-7 20-4-9-9-4z"/><path d="M22 2 11 13"/></svg>
                            <span class="sr-only">{{ __('Send') }}</span>
                        </button>
                    </div>
                    <p class="mt-1 hidden text-sm text-fg-danger" role="alert" data-composer-error></p>
                </form>

                <p class="px-4 pb-4 type-body-xs text-fg-tertiary sm:px-6">{{ __('Don’t share passwords or payment details. Conversations are stored according to our Privacy Policy.') }}</p>

                <noscript>
                    <p class="px-5 pb-5 text-sm text-fg">
                        {{ __('The assistant needs JavaScript.') }}
                        <a href="{{ route('quote') }}" class="font-semibold text-fg-brand underline">{{ __('Use the quote form instead') }}</a>
                    </p>
                </noscript>
            </section>

            <aside class="hidden h-fit rounded-3xl border border-v2-hairline bg-surface p-6 shadow-v2-card lg:sticky lg:top-24 lg:block" aria-labelledby="brief-title">
                <h2 id="brief-title" class="type-h5 text-fg">{{ __('Your project brief') }}</h2>
                <p class="mt-4 type-body-sm text-fg-secondary" data-progress-text>{{ __(':collected of :total details collected', ['collected' => $progress['collected'], 'total' => $progress['total']]) }}</p>
                <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-muted" role="progressbar" aria-labelledby="brief-title" aria-valuemin="0" aria-valuemax="{{ $progress['total'] }}" aria-valuenow="{{ $progress['collected'] }}" data-progress-bar>
                    <div class="h-full rounded-full bg-brand transition-[width]" style="width: 0%" data-progress-fill></div>
                </div>
                <ul class="mt-5 flex flex-col gap-4 type-body-md" data-progress-list>
                    @foreach ($progress['items'] as $item)
                        <li class="flex items-center gap-2.5 text-fg-tertiary">{{ $item['label'] }}</li>
                    @endforeach
                </ul>
                <p class="mt-5 rounded-lg bg-info-subtle p-3.5 type-body-sm text-fg-info">{{ __('After you confirm, our team receives your brief and contacts you within one business day.') }}</p>
                <p class="mt-3 type-body-xs text-fg-tertiary">
                    {{ __('Prefer a form?') }} <a href="{{ route('quote') }}" class="type-label-sm text-fg-brand hover:underline">{{ __('Request a Quote') }}</a>
                </p>
            </aside>
        </div>
    </div>
</x-layouts.public>
