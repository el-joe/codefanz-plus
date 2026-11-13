{{-- Legal template (Privacy Policy, Terms…): numbered clauses from the Markdown "##" headings with a contents sidebar. --}}
@php($email = app(\App\Services\Settings::class)->get('contact', 'email'))

<x-layouts.public
    :title="$translation->seo_title ?: $translation->title"
    :description="$translation->seo_description ?: (\Illuminate\Support\Str::limit(\Illuminate\Support\Str::squish(strip_tags($body)), 155) ?: $translation->title)"
    :seoable="$translation->page"
    :alternates="$alternates->mapWithKeys(fn ($alt) => [$alt->locale => route('pages.show', ['locale' => $alt->locale, 'slug' => $alt->slug])])->all()">
    <x-site.page-hero :title="$translation->title" :crumbs="[$translation->title => null]">
        <p class="type-body-sm text-fg-tertiary">{{ __('Last updated: :date', ['date' => $translation->updated_at->translatedFormat('j F Y')]) }}</p>
    </x-site.page-hero>

    <x-site.section tone="surface" inner="grid items-start gap-10 lg:grid-cols-[17.5rem_minmax(0,47.5rem)] lg:gap-20">
        @if ($toc !== [])
            <nav aria-labelledby="legal-contents" class="lg:sticky lg:top-24 lg:self-start">
                <div class="rounded-xl bg-subtle p-4 lg:p-5">
                    <h2 id="legal-contents" class="type-label-md text-fg">{{ __('Contents') }}</h2>
                    <ol class="mt-3 flex flex-col gap-2.5 type-body-sm" data-legal-toc>
                        @foreach ($toc as $entry)
                            <li><a href="#{{ $entry['id'] }}" class="rounded-sm text-fg-secondary transition-colors hover:text-fg-brand aria-[current=location]:text-fg-brand">{{ $entry['number'] }}. {{ $entry['title'] }}</a></li>
                        @endforeach
                    </ol>
                </div>
            </nav>
        @endif

        <article @class(['flex min-w-0 flex-col gap-8', 'lg:col-start-2' => $toc === []])>
            <div class="flex flex-col gap-4 type-body-md text-fg-secondary [&_a]:text-fg-brand [&_a]:underline [&_h2]:mt-6 [&_h2]:scroll-mt-24 [&_h2]:type-h4 [&_h2]:text-fg [&_h2:first-child]:mt-0 [&_h3]:mt-2 [&_h3]:type-h5 [&_h3]:text-fg [&_ol]:list-decimal [&_ol]:ps-6 [&_strong]:text-fg [&_ul]:list-disc [&_ul]:ps-6">
                {!! $body !!}
            </div>
            @if ($email)
                <p class="flex items-center gap-3 rounded-xl bg-brand-subtle px-5 py-4 type-label-md text-fg-brand">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="shrink-0"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
                    <span>{{ __('Questions about this page?') }} <a href="mailto:{{ $email }}" class="underline" dir="ltr">{{ $email }}</a></span>
                </p>
            @endif
        </article>
    </x-site.section>
</x-layouts.public>
