{{--
    Figma "Chip/Tech" with its public proficiency level: Verified (accent dot, brand border), Expertise (brand dot),
    Supported (grey dot). Technology names stay Latin in both languages. Expects proficiency flags preloaded.
--}}
@props(['technology', 'href' => null])

@php
    $level = $technology->proficiency();
    $styles = [
        'verified_in_projects' => ['chip' => 'border-line-brand', 'dot' => 'bg-accent'],
        'expertise' => ['chip' => 'border-line', 'dot' => 'bg-brand'],
        'supported' => ['chip' => 'border-line', 'dot' => 'bg-ink-300'],
    ][$level->value];
@endphp

<li {{ $attributes->merge(['class' => 'relative inline-flex items-center gap-2 rounded-full border bg-surface px-4 py-1.5 type-label-md text-fg transition-colors hover:border-line-brand '.$styles['chip']]) }} title="{{ $level->description() }}" data-level="{{ $level->value }}">
    <span class="size-2 shrink-0 rounded-full {{ $styles['dot'] }}" aria-hidden="true"></span>
    @if ($href)
        <a href="{{ $href }}" dir="auto" class="inline-flex min-h-6 items-center after:absolute after:inset-0 after:rounded-full hover:text-fg-brand">{{ $technology->translation()?->name ?? $technology->key }}</a>
    @else
        <span dir="auto">{{ $technology->translation()?->name ?? $technology->key }}</span>
    @endif
    <span class="sr-only">({{ $level->label() }})</span>
</li>
