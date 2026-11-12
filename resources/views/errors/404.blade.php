@php(\App\Services\ErrorPageLocale::apply(request()))
<x-layouts.error code="404" :title="__('This page can’t be found')" :message="__('The link may be broken or the page may have moved. Try searching or head to one of these pages.')">
    @php($locale = app()->getLocale())
    <div class="flex flex-wrap justify-center gap-3">
        <x-ui.button :href="route('home', ['locale' => $locale])">{{ __('Go to homepage') }}</x-ui.button>
        <x-ui.button :href="route('contact', ['locale' => $locale])" variant="secondary">{{ __('Contact us') }}</x-ui.button>
    </div>
    <form method="GET" action="{{ route('search', ['locale' => $locale]) }}" role="search" class="flex w-full max-w-md items-center gap-2 rounded-lg border border-line bg-surface px-3 focus-within:border-line-focus focus-within:ring-4 focus-within:ring-brand/20">
        <label for="error-search" class="sr-only">{{ __('Search the site') }}</label>
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" class="shrink-0 text-fg-tertiary"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
        <input id="error-search" type="search" name="q" maxlength="80" required placeholder="{{ __('Search the site…') }}" class="w-full bg-transparent py-2.5 text-base text-fg placeholder:text-fg-tertiary focus:outline-none">
        <button type="submit" class="rounded-md px-2 py-1 text-sm font-semibold text-fg-brand hover:bg-brand-subtle">{{ __('Search') }}</button>
    </form>
    <nav aria-label="{{ __('Popular pages') }}">
        <ul class="flex flex-wrap justify-center gap-2 text-sm">
            @foreach ([
                __('Services') => route('services.index', ['locale' => $locale]),
                __('Portfolio') => route('portfolio.index', ['locale' => $locale]),
                __('FAQ') => route('faq.index', ['locale' => $locale]),
                __('Request a Quote') => route('quote', ['locale' => $locale]),
            ] as $label => $url)
                <li><a href="{{ $url }}" class="inline-flex rounded-full border border-line bg-surface px-4 py-1.5 font-semibold text-fg hover:bg-subtle">{{ $label }}</a></li>
            @endforeach
        </ul>
    </nav>
</x-layouts.error>
