<x-layouts.public
    :title="$translation->seo_title ?: $translation->title"
    :description="$translation->seo_description ?: (\Illuminate\Support\Str::limit(\Illuminate\Support\Str::squish(strip_tags($body)), 155) ?: $translation->title)"
    :alternates="$alternates->mapWithKeys(fn ($alt) => [$alt->locale => route('pages.show', ['locale' => $alt->locale, 'slug' => $alt->slug])])->all()">
    <article class="mx-auto flex max-w-3xl flex-col gap-6 px-5 py-16 lg:py-24">
        <h1 class="text-4xl font-bold leading-tight text-fg">{{ $translation->title }}</h1>
        <div class="flex flex-col gap-4 text-lg leading-relaxed text-fg-secondary [&_a]:text-fg-brand [&_a]:underline [&_h2]:mt-6 [&_h2]:text-2xl [&_h2]:font-bold [&_h2]:text-fg [&_h3]:text-xl [&_h3]:font-semibold [&_h3]:text-fg [&_ol]:list-decimal [&_ol]:ps-6 [&_ul]:list-disc [&_ul]:ps-6">
            {!! $body !!}
        </div>
    </article>
</x-layouts.public>
