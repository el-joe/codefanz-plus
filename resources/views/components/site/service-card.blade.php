{{-- Figma "Card/Service": the whole card is one link; hover = brand border, Shadow/LG and a filled icon tile. Expects translations loaded. --}}
@props(['service', 'headingTag' => 'h3'])

@php($translation = $service->exactTranslation(app()->getLocale()))

<li {{ $attributes->merge(['class' => 'group relative flex flex-col items-start gap-4 rounded-xl border border-line bg-surface p-6 shadow-xs transition hover:border-line-brand hover:shadow-lg']) }}>
    <span class="flex size-12 items-center justify-center rounded-lg bg-brand-subtle text-icon-brand transition-colors group-hover:bg-brand group-hover:text-fg-on-brand" aria-hidden="true"><x-ui.icon :name="$service->icon ?? 'code'" :size="24" /></span>
    <{{ $headingTag }} class="type-h5 text-fg">
        <a href="{{ route('services.show', ['slug' => $translation->slug]) }}" class="after:absolute after:inset-0 after:rounded-xl focus-visible:outline-none focus-visible:after:outline-2 focus-visible:after:outline-offset-2 focus-visible:after:outline-line-focus">{{ $translation->title }}</a>
    </{{ $headingTag }}>
    @if ($translation->summary)
        <p class="type-body-sm text-fg-secondary">{{ $translation->summary }}</p>
    @endif
    <span class="mt-auto inline-flex items-center gap-1.5 type-label-md text-fg-brand" aria-hidden="true">
        {{ __('Explore service') }}
        <x-ui.arrow :size="16" class="transition-transform group-hover:translate-x-0.5 rtl:group-hover:-translate-x-0.5" />
    </span>
</li>
