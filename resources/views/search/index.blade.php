<x-layouts.public
    :title="$term !== '' ? __('Search results for “:term”', ['term' => $term]) : __('Search')"
    :description="__('Search CodeFanz services, projects, products, articles and pages.')"
    robots="noindex"
    :alternates="\App\Models\Language::active()->keys()->mapWithKeys(fn ($code) => [$code => route('search', ['locale' => $code])])->all()">
    <x-site.page-hero :title="__('Search')" :crumbs="[__('Search') => null]">
        <form method="GET" action="{{ route('search') }}" role="search" class="flex w-full max-w-xl items-center gap-2 rounded-lg border border-line bg-surface px-3 focus-within:border-line-focus focus-within:ring-4 focus-within:ring-brand/20">
            <label for="site-search" class="sr-only">{{ __('Search the site') }}</label>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" class="shrink-0 text-fg-tertiary"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <input id="site-search" type="search" name="q" value="{{ $term }}" maxlength="80" required minlength="2" placeholder="{{ __('Search the site…') }}" class="w-full bg-transparent py-2.5 text-base text-fg placeholder:text-fg-tertiary focus:outline-none">
            <button type="submit" class="rounded-md px-2 py-1 text-sm font-semibold text-fg-brand hover:bg-brand-subtle">{{ __('Search') }}</button>
        </form>
    </x-site.page-hero>

    <div class="mx-auto flex max-w-7xl flex-col gap-10 px-5 py-14 lg:px-20" aria-live="polite">
        @if ($term === '')
            <p class="text-fg-secondary">{{ __('Type at least two characters to search.') }}</p>
        @elseif ($groups === [])
            <x-ui.empty-state class="rounded-xl border border-dashed border-line" :title="__('No results for “:term”', ['term' => $term])" :description="__('Try another word, or contact us and we will point you in the right direction.')" />
        @else
            @foreach ($groups as $heading => $results)
                <section aria-labelledby="results-{{ $loop->index }}" class="flex flex-col gap-4">
                    <h2 id="results-{{ $loop->index }}" class="text-xl font-bold text-fg">{{ $heading }}</h2>
                    <ul class="flex flex-col gap-3">
                        @foreach ($results as $result)
                            <li class="rounded-xl border border-line bg-surface p-4">
                                <a href="{{ $result['url'] }}" class="font-semibold text-fg hover:text-fg-brand">{{ $result['title'] }}</a>
                                @if ($result['summary'])
                                    <p class="text-sm text-fg-secondary">{{ $result['summary'] }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach
        @endif
    </div>
</x-layouts.public>
