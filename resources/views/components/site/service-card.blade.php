{{--
    Figma "Card/Service" (v2): radius 24, hairline, v2 card shadow, gradient icon tile. The whole card is one link; hover =
    lift shadow, brand border at 30%, −4px and the arrow nudges 4px toward the reading direction. Expects translations loaded.
--}}
@props(['service', 'headingTag' => 'h3'])

@php($translation = $service->exactTranslation(app()->getLocale()))

<li {{ $attributes->merge(['class' => 'group relative flex flex-col items-start gap-4 rounded-3xl border border-v2-hairline bg-surface p-6 shadow-v2-card transition duration-200 ease-out hover:-translate-y-1 hover:border-brand-600/30 hover:shadow-v2-lift']) }}>
    <span class="flex size-12 items-center justify-center rounded-lg tile-v2 text-icon-brand" aria-hidden="true"><x-ui.icon :name="$service->icon ?? 'code'" :size="24" /></span>
    <{{ $headingTag }} class="type-h5 text-fg">
        <a href="{{ route('services.show', ['slug' => $translation->slug]) }}" class="after:absolute after:inset-0 after:rounded-3xl focus-visible:outline-none focus-visible:after:outline-2 focus-visible:after:outline-offset-2 focus-visible:after:outline-line-focus">{{ $translation->title }}</a>
    </{{ $headingTag }}>
    @if ($translation->summary)
        <p class="type-body-sm text-fg-secondary">{{ $translation->summary }}</p>
    @endif
    <span class="mt-auto inline-flex items-center gap-1.5 type-label-md text-fg-brand" aria-hidden="true">
        {{ __('Explore service') }}
        <x-ui.arrow :size="16" class="transition-transform duration-200 group-hover:translate-x-1 rtl:group-hover:-translate-x-1" />
    </span>
</li>
