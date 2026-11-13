{{-- Figma "Card/Industry": accent icon tile, name and typical solutions. Links to the industry on the Industries page. --}}
@props(['industry', 'headingTag' => 'h3'])

@php($translation = $industry->exactTranslation(app()->getLocale()))

<li {{ $attributes->merge(['class' => 'group relative flex items-center gap-3 rounded-lg border border-line bg-surface p-4 transition hover:border-line-brand hover:shadow-md']) }}>
    <span class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-accent-subtle text-fg-accent" aria-hidden="true"><x-ui.icon :name="$industry->icon ?? 'briefcase'" :size="22" /></span>
    <span class="flex min-w-0 flex-1 flex-col gap-0.5">
        <{{ $headingTag }} class="type-label-lg text-fg">
            <a href="{{ route('industries.index') }}#{{ $translation->slug }}" class="after:absolute after:inset-0 after:rounded-lg focus-visible:outline-none focus-visible:after:outline-2 focus-visible:after:outline-offset-2 focus-visible:after:outline-line-focus">{{ $translation->name }}</a>
        </{{ $headingTag }}>
        @if ($translation->typical_solutions)
            <span class="truncate type-body-xs text-fg-tertiary">{{ implode(', ', array_slice($translation->typical_solutions, 0, 3)) }}</span>
        @endif
    </span>
</li>
