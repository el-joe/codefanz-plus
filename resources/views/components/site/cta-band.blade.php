{{--
    Figma "CTA": gradient card (brand/700 → brand/900 → accent/700) with heading, lead, the primary action and,
    when a WhatsApp number is configured, a "Chat on WhatsApp" button. Defaults to the Request a Quote form.
--}}
@props(['title' => null, 'lead' => null, 'action' => null, 'href' => null, 'tone' => 'canvas'])

@php
    $whatsapp = app(\App\Services\Settings::class)->get('contact', 'whatsapp');
@endphp

<x-site.section :tone="$tone" inner="" {{ $attributes }}>
    <div class="dark relative flex flex-col gap-8 overflow-hidden rounded-2xl bg-[linear-gradient(148deg,var(--color-brand-700)_7.7%,var(--color-brand-900)_57.7%,var(--color-accent-700)_84.6%)] px-6 py-10 text-fg sm:p-10 lg:flex-row lg:items-center lg:p-16">
        <div class="flex min-w-0 flex-1 flex-col gap-3">
            <h2 class="type-h2 text-balance text-fg">{{ $title ?? __('Have an idea? Let’s build it together.') }}</h2>
            <p class="type-body-lg text-fg-secondary">{{ $lead ?? __('Get a free preliminary estimate within 48 hours. No payment required.') }}</p>
        </div>
        <div class="flex flex-col gap-3 sm:flex-row lg:shrink-0">
            <x-ui.button :href="$href ?? route('quote')" size="lg" arrow>{{ $action ?? __('Start Your Project') }}</x-ui.button>
            @if ($whatsapp)
                <x-ui.button :href="\App\Services\ContactChannels::whatsappUrl($whatsapp)" variant="secondary" size="lg" target="_blank" rel="noopener noreferrer">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="shrink-0"><path d="m4 20 1.3-4A8.5 8.5 0 1 1 8.1 19z"/><path d="M9 9.5c0 3 2.5 5.5 5.5 5.5l1-1.5-2-1-1 .8A3.5 3.5 0 0 1 10.7 11.5l.8-1-1-2z"/></svg>
                    {{ __('Chat on WhatsApp') }}
                </x-ui.button>
            @endif
        </div>
    </div>
</x-site.section>
