@props(['name', 'size' => 20])

@php($icon = $name instanceof \App\Enums\ContentIcon ? $name : \App\Enums\ContentIcon::tryFrom((string) $name))

@if ($icon)
    <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" {{ $attributes }}>{!! $icon->svg() !!}</svg>
@endif
