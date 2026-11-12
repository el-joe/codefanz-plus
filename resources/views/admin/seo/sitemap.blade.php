{{-- A12 · Sitemap & robots overview (read-only; indexing is switched in Settings → SEO). --}}
<x-layouts.admin :title="__('Sitemap & robots')" :crumb="__('Content & SEO')">
    @include('admin.seo._tabs')

    @if ($blocksIndexing)
        <x-ui.alert tone="warning" :title="__('Search engines are blocked')">
            {{ __('robots.txt disallows everything and every response carries X-Robots-Tag: noindex. This is the default outside production.') }}
            @can('settings.manage')
                <a href="{{ route('admin.settings.edit', 'seo') }}" class="font-semibold underline">{{ __('Change in Settings → SEO') }}</a>
            @endcan
        </x-ui.alert>
    @else
        <x-ui.alert tone="success" :title="__('Indexing is allowed')">{{ __('Search engines may crawl the public site. Private areas and site search stay disallowed.') }}</x-ui.alert>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <x-ui.card :title="__('XML sitemaps')">
            <p class="text-sm text-fg-secondary">{{ trans_choice('{0} No URLs are listed yet.|{1} 1 published, indexable URL is listed.|[2,*] :count published, indexable URLs are listed.', $urlCount, ['count' => $urlCount]) }}
                {{ __('Drafts, closed positions, unlisted projects, noindex and canonicalised pages are left out. Sitemaps rebuild automatically when content changes.') }}</p>
            <p class="mt-4"><a href="{{ route('sitemap.index') }}" target="_blank" rel="noopener" class="font-mono text-sm text-fg-brand hover:underline" dir="ltr">{{ route('sitemap.index') }}</a></p>
            <ul class="mt-3 flex flex-col gap-1.5">
                @forelse ($children as $name)
                    <li><a href="{{ route('sitemap.child', ['name' => $name]) }}" target="_blank" rel="noopener" class="font-mono text-xs text-fg-secondary hover:text-fg-brand" dir="ltr">{{ route('sitemap.child', ['name' => $name]) }}</a></li>
                @empty
                    <li class="text-sm text-fg-tertiary">{{ __('No child sitemaps yet.') }}</li>
                @endforelse
            </ul>
        </x-ui.card>

        <x-ui.card :title="__('robots.txt')">
            <pre class="overflow-x-auto rounded-md bg-subtle p-4 font-mono text-xs text-fg" dir="ltr">{{ $robots }}</pre>
            <p class="mt-3"><a href="{{ route('robots') }}" target="_blank" rel="noopener" class="font-mono text-sm text-fg-brand hover:underline" dir="ltr">{{ route('robots') }}</a></p>
        </x-ui.card>
    </div>
</x-layouts.admin>
