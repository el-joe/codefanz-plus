{{-- Contact page: channels from Settings → Contact and a small message form (Admin → Contact inbox). --}}
@php
    $icons = [
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
        'whatsapp' => '<path d="M3 21l1.65-3.8A9 9 0 1 1 7.8 20.4z"/>',
        'phone' => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8 9.8a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2z"/>',
        'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
        'bot' => '<rect x="4" y="8" width="16" height="12" rx="2"/><path d="M12 8V4M8 14h.01M16 14h.01M9 18h6"/>',
    ];
    $languages = \App\Models\Language::active();
    $sent = session('contact_sent');
@endphp

<x-layouts.public
    :title="__('Contact us')"
    :description="__('Choose the channel that suits you. We reply within one business day — in Arabic or English.')"
    :alternates="$languages->keys()->mapWithKeys(fn ($code) => [$code => route('contact', ['locale' => $code])])->all()">
    <x-site.page-hero :eyebrow="__('Contact us')" :title="__('Let’s talk about your project')"
                      :lead="__('Choose the channel that suits you. We reply within one business day — in Arabic or English.')"
                      :crumbs="[__('Contact') => null]" />

    <x-site.section tone="surface" inner="grid grid-cols-[minmax(0,1fr)] items-start gap-8 lg:grid-cols-[24rem_minmax(0,1fr)] lg:gap-10">
        <section aria-labelledby="contact-channels">
            <h2 id="contact-channels" class="sr-only">{{ __('Contact channels') }}</h2>
            <ul class="flex flex-col gap-4" data-contact-channels>
                @foreach ($channels as $channel)
                    @php
                        $inner = '<span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-brand-subtle text-icon-brand"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$icons[$channel['icon']].'</svg></span>';
                    @endphp
                    <li data-channel="{{ $channel['key'] }}">
                        @if ($channel['href'])
                            <a href="{{ $channel['href'] }}" @if ($channel['external']) target="_blank" rel="noopener noreferrer" @endif
                               class="group flex items-center gap-4 rounded-xl border border-line bg-surface p-5 shadow-xs transition hover:border-line-brand hover:shadow-md">
                                {!! $inner !!}
                                <span class="flex min-w-0 flex-1 flex-col gap-0.5">
                                    <span class="type-label-lg text-fg">{{ $channel['label'] }}</span>
                                    <span class="truncate type-body-md text-fg-brand" @if (in_array($channel['key'], ['email', 'whatsapp', 'phone'], true)) dir="ltr" @endif>{{ $channel['value'] }}</span>
                                    @if ($channel['hint'])
                                        <span class="type-body-xs text-fg-tertiary">{{ $channel['hint'] }}</span>
                                    @endif
                                </span>
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="shrink-0 text-icon-secondary transition-transform group-hover:translate-x-0.5 rtl:-scale-x-100 rtl:group-hover:-translate-x-0.5"><path d="m9 6 6 6-6 6"/></svg>
                                @if ($channel['external'])
                                    <span class="sr-only">{{ __('(opens in a new tab)') }}</span>
                                @endif
                            </a>
                        @else
                            <div class="flex items-center gap-4 rounded-xl border border-dashed border-line bg-surface p-5" aria-disabled="true">
                                {!! $inner !!}
                                <span class="flex min-w-0 flex-1 flex-col gap-0.5">
                                    <span class="type-label-lg text-fg">{{ $channel['label'] }}</span>
                                    @if ($channel['hint'])
                                        <span class="type-body-xs text-fg-tertiary">{{ $channel['hint'] }}</span>
                                    @endif
                                </span>
                                <x-ui.badge tone="neutral" :dot="false">{{ __('Coming soon') }}</x-ui.badge>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>

        <section aria-labelledby="contact-form-title" class="rounded-2xl border border-line bg-surface p-6 shadow-xs sm:p-8">
            <h2 id="contact-form-title" class="type-h4 text-fg sm:type-h3">{{ __('Send us a message') }}</h2>

            @if ($sent)
                <x-ui.alert tone="success" :title="__('Thank you — your message is on its way.')" class="mt-6" data-contact-sent>
                    {{ __('We reply within one business day. If it is urgent, use one of the channels on this page.') }}
                </x-ui.alert>
            @else
                @if ($errors->any())
                    <x-ui.alert tone="danger" :title="__('Please fix the highlighted fields')" class="mt-6">
                        {{ $errors->first('form') ?: __('Some fields need attention.') }}
                    </x-ui.alert>
                @endif

                <form method="POST" action="{{ route('contact.store') }}" class="mt-6 flex flex-col gap-5" novalidate>
                    @csrf
                    <input type="hidden" name="{{ \App\Http\Requests\ContactMessageRequest::TIMESTAMP }}" value="{{ $formToken }}">
                    {{-- Honeypot: hidden from people and assistive technology; bots tend to fill it. --}}
                    <div class="hidden" aria-hidden="true">
                        <label for="contact-website">{{ __('Leave this field empty') }}</label>
                        <input type="text" id="contact-website" name="{{ \App\Http\Requests\ContactMessageRequest::HONEYPOT }}" tabindex="-1" autocomplete="off">
                    </div>

                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <x-ui.input name="name" size="lg" icon="user" :label="__('Full name').' *'" required maxlength="120" autocomplete="name" :placeholder="__('Your name')" />
                        <x-ui.input name="email" size="lg" icon="mail" type="email" :label="__('Email').' *'" required maxlength="255" autocomplete="email" dir="ltr" placeholder="you@company.com" />
                        <x-ui.input name="phone" size="lg" icon="phone" type="tel" :label="__('Phone')" maxlength="30" autocomplete="tel" dir="ltr" placeholder="+20 …" />
                        <x-ui.select name="topic" size="lg" :label="__('Subject').' *'" :options="\App\Enums\ContactTopic::options()" :value="$topic->value" required />
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label for="message" class="type-label-md text-fg">{{ __('Message') }} *</label>
                        <textarea id="message" name="message" rows="5" required minlength="10" maxlength="2000" aria-describedby="@error('message') message-error @enderror message-help"
                                  data-char-count="2000" @error('message') aria-invalid="true" @enderror placeholder="{{ __('How can we help?') }}"
                                  class="w-full rounded-md border bg-surface px-3 py-2.5 type-body-md text-fg placeholder:text-fg-tertiary focus:border-line-focus focus:outline-none focus:ring-4 focus:ring-brand/20 {{ $errors->has('message') ? 'border-line-danger' : 'border-line-strong' }}">{{ old('message', $prefill) }}</textarea>
                        @error('message')
                            <p id="message-error" class="text-sm text-fg-danger">{{ $message }}</p>
                        @enderror
                        <div class="flex items-start justify-between gap-4">
                            <p id="message-help" class="type-body-xs text-fg-secondary">{{ __('Please don’t share passwords or payment details. Up to 2,000 characters.') }}</p>
                            <span class="shrink-0 type-body-xs text-fg-tertiary tabular-nums" dir="ltr" data-char-count-output data-char-count-quiet aria-hidden="true"></span>
                        </div>
                    </div>

                    <fieldset class="flex flex-col gap-2">
                        <legend class="mb-2 type-label-md text-fg">{{ __('Preferred language for our reply') }}</legend>
                        <div class="flex flex-wrap gap-5">
                            @foreach ($languages as $code => $language)
                                <label class="flex items-center gap-2 type-body-md text-fg">
                                    <input type="radio" name="preferred_locale" value="{{ $code }}" @checked(old('preferred_locale', app()->getLocale()) === $code) class="size-4 accent-brand">
                                    <span lang="{{ $code }}">{{ $language['native_name'] }}</span>
                                </label>
                            @endforeach
                        </div>
                        @error('preferred_locale')
                            <p class="text-sm text-fg-danger">{{ $message }}</p>
                        @enderror
                    </fieldset>

                    <div class="flex flex-col gap-1.5">
                        <label class="flex items-start gap-3 type-body-md text-fg">
                            <input type="checkbox" name="consent" value="1" required @checked(old('consent')) @error('consent') aria-invalid="true" aria-describedby="consent-error" @enderror class="mt-1 size-4 shrink-0 accent-brand">
                            <span>{{ __('I agree to the Privacy Policy and to being contacted about my request.') }}</span>
                        </label>
                        @error('consent')
                            <p id="consent-error" class="text-sm text-fg-danger">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex flex-col items-start gap-4">
                        <x-ui.button size="lg" arrow>{{ __('Send message') }}</x-ui.button>
                        <p class="flex items-center gap-2 type-body-xs text-fg-tertiary">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="shrink-0 text-icon-success"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
                            {{ __('Protected by rate limiting and spam checks. No CAPTCHA puzzles.') }}
                        </p>
                    </div>
                </form>
            @endif
        </section>
    </x-site.section>
</x-layouts.public>
