{{--
    Floating AI Project Assistant launcher (owner request; Figma has no launcher component, so it uses the brand
    indigo button with the teal "online" accent from the assistant header). A plain link to /{locale}/assistant:
    no third-party widget, works without JavaScript. Fixed at the bottom-end corner (bottom-left in Arabic), below
    the cookie banner (z-40) and the toast region (z-50), hidden while the cookie banner is open and when printing.
--}}
<a href="{{ route('assistant') }}" data-assistant-launcher aria-label="{{ __('Chat with our AI assistant') }}"
   class="group fixed end-4 bottom-4 z-30 flex size-14 items-center justify-center rounded-full bg-brand text-fg-on-brand shadow-lg transition hover:-translate-y-0.5 hover:bg-brand-hover hover:shadow-xl focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-line-focus sm:end-6 sm:bottom-6 print:hidden">
    <span class="absolute inset-0 rounded-full bg-brand motion-safe:animate-[launcher-pulse_1.8s_ease-out_1s_2]" aria-hidden="true"></span>
    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="relative"><path d="M21 12a8 8 0 0 1-11.6 7.1L4 20.5l1.4-4.9A8 8 0 1 1 21 12Z"/><path d="M8.5 11h.01M12 11h.01M15.5 11h.01"/></svg>
    <span class="absolute end-0.5 top-0.5 size-3.5 rounded-full border-2 border-surface bg-accent" aria-hidden="true"></span>
    <span class="pointer-events-none absolute end-full top-1/2 me-3 -translate-y-1/2 whitespace-nowrap rounded-md bg-ink-900 px-3 py-1.5 type-label-sm text-white opacity-0 shadow-md transition-opacity group-hover:opacity-100 group-focus-visible:opacity-100 max-sm:hidden" aria-hidden="true">{{ __('Chat with our AI assistant') }}</span>
</a>
