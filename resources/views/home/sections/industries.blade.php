<section class="mx-auto flex max-w-7xl flex-col gap-10 px-5 py-16 lg:px-20 lg:py-24" aria-labelledby="{{ $headingId }}" data-section="industries">
    <x-site.section-heading :id="$headingId" :eyebrow="$copy['eyebrow']" :heading="$copy['heading']" :subheading="$copy['subheading']" />
    <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($data as $industry)
            @php($translation = $industry->exactTranslation(app()->getLocale()))
            <li class="relative flex items-start gap-3 rounded-xl border border-line bg-surface p-5 hover:border-line-brand">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-accent-subtle text-fg-accent"><x-ui.icon :name="$industry->icon ?? 'briefcase'" /></span>
                <span class="flex min-w-0 flex-col gap-1">
                    <h3 class="font-semibold text-fg"><a href="{{ route('industries.index') }}#{{ $translation->slug }}" class="after:absolute after:inset-0">{{ $translation->name }}</a></h3>
                    @if ($translation->typical_solutions)
                        <span class="text-xs text-fg-secondary">{{ implode(' · ', array_slice($translation->typical_solutions, 0, 3)) }}</span>
                    @endif
                </span>
            </li>
        @endforeach
    </ul>
    @if ($copy['cta_label'])
        <div class="flex justify-center"><a href="{{ route('industries.index') }}" class="text-sm font-semibold text-fg-brand hover:underline">{{ $copy['cta_label'] }}</a></div>
    @endif
</section>
