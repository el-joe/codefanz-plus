{{-- Technology chip with its public proficiency level (Verified / Expertise / Supported). Expects proficiency flags preloaded. --}}
@props(['technology'])

@php
    $level = $technology->proficiency();
    $styles = [
        'verified_in_projects' => ['chip' => 'border-line-brand', 'dot' => 'bg-accent'],
        'expertise' => ['chip' => 'border-line', 'dot' => 'bg-brand'],
        'supported' => ['chip' => 'border-line', 'dot' => 'bg-fg-tertiary'],
    ][$level->value];
@endphp

<li {{ $attributes->merge(['class' => 'inline-flex items-center gap-2 rounded-full border bg-surface px-3 py-1.5 text-sm font-medium text-fg '.$styles['chip']]) }} title="{{ $level->description() }}" data-level="{{ $level->value }}">
    <span class="size-2 rounded-full {{ $styles['dot'] }}" aria-hidden="true"></span>
    <span dir="auto">{{ $technology->translation()?->name ?? $technology->key }}</span>
    <span class="sr-only">({{ $level->label() }})</span>
</li>
