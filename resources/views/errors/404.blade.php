@php(\App\Services\ErrorPageLocale::apply(request()))
<x-layouts.error code="404" :title="__('This page can’t be found')" :message="__('The link may be broken or the page may have moved. Try searching or head to one of these pages.')">
    @php($locale = app()->getLocale())
    <div class="flex flex-wrap justify-center gap-3">
        <x-ui.button :href="route('home', ['locale' => $locale])" size="lg">{{ __('Go to homepage') }}</x-ui.button>
        <x-ui.button :href="route('contact', ['locale' => $locale])" variant="secondary" size="lg">{{ __('Contact us') }}</x-ui.button>
    </div>
    <form method="GET" action="{{ route('search', ['locale' => $locale]) }}" role="search" class="flex h-12 w-full max-w-md items-center gap-2 rounded-md border border-line bg-surface ps-3 pe-1.5 shadow-xs focus-within:border-line-focus focus-within:ring-4 focus-within:ring-brand/20">
        <label for="error-search" class="sr-only">{{ __('Search the site') }}</label>
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="shrink-0 text-icon-secondary"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
        <input id="error-search" type="search" name="q" maxlength="80" required placeholder="{{ __('Search the site…') }}" class="w-full bg-transparent type-body-md text-fg placeholder:text-fg-tertiary focus:outline-none">
        <button type="submit" class="rounded-md px-3 py-1.5 type-label-md text-fg-brand hover:bg-brand-subtle">{{ __('Search') }}</button>
    </form>
    <nav aria-label="{{ __('Popular pages') }}">
        <ul class="flex flex-wrap justify-center gap-2">
            @foreach ([
                __('Services') => route('services.index', ['locale' => $locale]),
                __('Portfolio') => route('portfolio.index', ['locale' => $locale]),
                __('Blog') => route('blog.index', ['locale' => $locale]),
                __('Request a Quote') => route('quote', ['locale' => $locale]),
            ] as $label => $url)
                <li><a href="{{ $url }}" class="inline-flex rounded-full border border-line bg-surface px-4 py-2 type-label-md text-fg transition-colors hover:border-line-brand hover:text-fg-brand">{{ $label }}</a></li>
            @endforeach
        </ul>
    </nav>
</x-layouts.error>
