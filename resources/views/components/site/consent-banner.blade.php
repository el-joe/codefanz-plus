{{-- Cookie consent: necessary-only by default; analytics (GA4) only after an explicit opt-in. Works without JavaScript (form posts). --}}
@props(['measurementId', 'decided' => false])

<section data-consent-banner data-ga-id="{{ $measurementId }}" data-cookie="{{ \App\Services\Analytics\AnalyticsConsent::COOKIE }}"
         data-max-age="{{ 60 * 60 * 24 * \App\Services\Analytics\AnalyticsConsent::LIFETIME_DAYS }}"
         @if ($decided) hidden @endif
         aria-labelledby="consent-title"
         class="fixed inset-x-4 bottom-4 z-40 mx-auto max-w-2xl rounded-xl border border-line bg-surface-raised p-5 text-fg shadow-xl sm:inset-x-6">
    <h2 id="consent-title" class="text-base font-semibold">{{ __('Cookies on this site') }}</h2>
    <p class="mt-1 text-sm text-fg-secondary">{{ __('We use only the cookies needed to run the site. With your permission we also use Google Analytics to understand which pages help visitors. You can change this at any time from “Cookie settings” in the footer.') }}</p>
    <div class="mt-4 flex flex-wrap gap-2">
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
