<x-layouts.public
    :title="$term !== '' ? __('Search results for “:term”', ['term' => $term]) : __('Search')"
    :description="__('Search CodeFanz services, projects, products, articles and pages.')"
    robots="noindex"
    :alternates="\App\Models\Language::active()->keys()->mapWithKeys(fn ($code) => [$code => route('search', ['locale' => $code])])->all()">
    <x-site.page-hero :title="__('Search')" :crumbs="[__('Search') => null]">
        <form method="GET" action="{{ route('search') }}" role="search" class="flex h-12 w-full max-w-xl items-center gap-2 rounded-md border border-line bg-surface ps-3 pe-1.5 shadow-xs focus-within:border-line-focus focus-within:ring-4 focus-within:ring-brand/20">
            <label for="site-search" class="sr-only">{{ __('Search the site') }}</label>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="shrink-0 text-icon-secondary"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <input id="site-search" type="search" name="q" value="{{ $term }}" maxlength="80" required minlength="2" placeholder="{{ __('Search the site…') }}" class="w-full bg-transparent type-body-md text-fg placeholder:text-fg-tertiary focus:outline-none">
            <button type="submit" class="rounded-md px-3 py-1.5 type-label-md text-fg-brand hover:bg-brand-subtle">{{ __('Search') }}</button>
        </form>
    </x-site.page-hero>

    <x-site.section tone="surface" inner="flex flex-col gap-10 [&>*]:max-w-4xl" aria-live="polite">
        @if ($term === '')
            <p class="type-body-md text-fg-secondary">{{ __('Type at least two characters to search.') }}</p>
        @elseif ($groups === [])
            <x-ui.empty-state class="rounded-xl border border-dashed border-line" :title="__('No results for “:term”', ['term' => $term])" :description="__('Try another word, or contact us and we will point you in the right direction.')" />
        @else
            @foreach ($groups as $heading => $results)
                <section aria-labelledby="results-{{ $loop->index }}" class="flex flex-col gap-4">
                    <h2 id="results-{{ $loop->index }}" class="type-h4 text-fg">{{ $heading }}</h2>
                    <ul class="flex flex-col gap-3">
                        @foreach ($results as $result)
                            <li class="group relative flex items-center gap-4 rounded-xl border border-line bg-surface px-5 py-4 shadow-xs transition hover:border-line-brand hover:shadow-md">
                                <div class="flex min-w-0 flex-1 flex-col gap-1">
                                    <a href="{{ $result['url'] }}" class="type-label-lg text-fg after:absolute after:inset-0 after:rounded-xl group-hover:text-fg-brand focus-visible:outline-none focus-visible:after:outline-2 focus-visible:after:outline-offset-2 focus-visible:after:outline-line-focus">{{ $result['title'] }}</a>
                                    @if ($result['summary'])
                                        <p class="type-body-sm text-fg-secondary">{{ $result['summary'] }}</p>
                                    @endif
                                </div>
                                <x-ui.arrow class="text-icon-secondary group-hover:text-icon-brand" />
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach
        @endif
    </x-site.section>
</x-layouts.public>
