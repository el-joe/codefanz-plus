{{-- Cookie consent: necessary-only by default; analytics (GA4) only after an explicit opt-in. Works without JavaScript (form posts). --}}
@props(['measurementId', 'decided' => false])

<section data-consent-banner data-ga-id="{{ $measurementId }}" data-cookie="{{ \App\Services\Analytics\AnalyticsConsent::COOKIE }}"
         data-max-age="{{ 60 * 60 * 24 * \App\Services\Analytics\AnalyticsConsent::LIFETIME_DAYS }}"
         @if ($decided) hidden @endif
         aria-labelledby="consent-title"
         class="fixed inset-x-4 bottom-4 z-40 mx-auto flex max-w-3xl flex-col gap-4 rounded-xl border border-line bg-surface-raised p-5 text-fg shadow-xl sm:inset-x-6 sm:flex-row sm:items-center sm:gap-6 sm:p-6">
    <span class="hidden size-11 shrink-0 items-center justify-center rounded-lg bg-brand-subtle text-icon-brand sm:flex" aria-hidden="true">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a9 9 0 1 0 9 9 4 4 0 0 1-5-5 4 4 0 0 1-4-4Z"/><path d="M8.5 13.5h.01M12 16h.01M15.5 14.5h.01M9 9.5h.01"/></svg>
    </span>
    <div class="flex min-w-0 flex-1 flex-col gap-1">
        <h2 id="consent-title" class="type-label-lg text-fg">{{ __('Cookies on this site') }}</h2>
        <p class="type-body-sm text-fg-secondary">{{ __('We use only the cookies needed to run the site. With your permission we also use Google Analytics to understand which pages help visitors. You can change this at any time from “Cookie settings” in the footer.') }}</p>
    </div>
    <div class="flex shrink-0 flex-wrap gap-2">
        <form method="POST" action="{{ route('consent.store') }}">
            @csrf
            <input type="hidden" name="choice" value="{{ \App\Services\Analytics\AnalyticsConsent::NECESSARY }}">
            <x-ui.button type="submit" variant="secondary" data-consent-choice="{{ \App\Services\Analytics\AnalyticsConsent::NECESSARY }}">{{ __('Necessary only') }}</x-ui.button>
        </form>
        <form method="POST" action="{{ route('consent.store') }}">
            @csrf
            <input type="hidden" name="choice" value="{{ \App\Services\Analytics\AnalyticsConsent::ANALYTICS }}">
            <x-ui.button type="submit" data-consent-choice="{{ \App\Services\Analytics\AnalyticsConsent::ANALYTICS }}">{{ __('Allow analytics') }}</x-ui.button>
        </form>
    </div>
</section>
