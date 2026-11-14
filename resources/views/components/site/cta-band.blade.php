{{--
    Figma "CTA" (v2): night card with the generated glow texture anchored at the inline end (mirrored in RTL), radius
    32 (24 on mobile), dark hairline; heading, lead, the primary action and, when a WhatsApp number is configured,
    a "Chat on WhatsApp" button. Defaults to the Request a Quote form.
--}}
@props(['title' => null, 'lead' => null, 'action' => null, 'href' => null, 'tone' => 'canvas'])

@php
    $whatsapp = app(\App\Services\Settings::class)->get('contact', 'whatsapp');
@endphp

<x-site.section :tone="$tone" inner="" {{ $attributes }}>
    <div data-reveal class="dark relative isolate flex flex-col gap-8 overflow-hidden rounded-3xl border border-v2-hairline-dark bg-v2-night px-6 py-12 text-fg sm:p-12 lg:flex-row lg:items-center lg:justify-between lg:rounded-4xl lg:px-16 lg:py-20">
        <div class="absolute inset-0 -z-10" aria-hidden="true"><x-site.image name="cta-dark-glow" sizes="(min-width: 1440px) 1280px, 100vw" class="size-full object-cover object-right opacity-40 rtl:-scale-x-100 lg:opacity-90" /></div>
        {{-- The copy stays in the dark half of the texture (AA contrast); actions sit over the glow. --}}
        <div class="flex min-w-0 flex-1 flex-col gap-3 lg:max-w-[38rem]">
            <h2 class="type-v2-h2-mobile text-balance text-white lg:type-h2">{{ $title ?? __('Have an idea? Let’s build it together.') }}</h2>
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
