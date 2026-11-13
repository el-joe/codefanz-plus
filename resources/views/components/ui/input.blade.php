{{--
    Figma "Input": label, field, helper/error. size md (admin forms) | lg (48px public forms);
    icon = user | mail | phone | globe | code | calendar | search adds the leading 20px icon (Figma "Input / with icon").
--}}
@props(['name', 'label', 'type' => 'text', 'value' => null, 'helper' => null, 'dir' => null, 'bag' => 'default', 'size' => 'md', 'icon' => null])

@php
    $key = trim(str_replace(['[', ']'], ['.', ''], $name), '.');
    $id = $attributes->get('id', $key);
    $bagErrors = $errors->getBag($bag);
    $hasError = $bagErrors->has($key);
    $icons = [
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
        'phone' => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8 9.8a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2z"/>',
        'globe' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>',
        'code' => '<path d="m8 8-4 4 4 4M16 8l4 4-4 4"/>',
        'calendar' => '<rect x="3" y="4" width="18" height="17" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
    ];
    $iconPath = $icon ? ($icons[$icon] ?? null) : null;
    $sizing = $size === 'lg' ? 'h-12 type-body-md' : 'py-2.5 text-base';
    $padding = $iconPath ? 'ps-10 pe-3' : 'px-3';
@endphp

<div class="flex flex-col gap-1.5">
    <label for="{{ $id }}" class="{{ $size === 'lg' ? 'type-label-md' : 'text-sm font-semibold' }} text-fg">{{ $label }}</label>
    @if ($iconPath)<div class="relative" @if ($dir) dir="{{ $dir }}" @endif>@endif
    @if ($iconPath)
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="pointer-events-none absolute start-3 top-1/2 -translate-y-1/2 text-icon-secondary">{!! $iconPath !!}</svg>
    @endif
    <input
        id="{{ $id }}"
        name="{{ $name }}"
        type="{{ $type }}"
        value="{{ $type === 'password' ? '' : old($key, $value) }}"
        @if ($dir) dir="{{ $dir }}" @endif
        @if ($hasError || $helper) aria-describedby="{{ $id }}-help" @endif
        @if ($hasError) aria-invalid="true" @endif
        {{ $attributes->except(['id', 'bag'])->merge(['class' => 'w-full rounded-md border bg-surface '.$sizing.' '.$padding.' text-fg placeholder:text-fg-tertiary focus:border-line-focus focus:outline-none focus:ring-4 focus:ring-brand/20 '.($hasError ? 'border-line-danger' : 'border-line')]) }}
    >
    @if ($iconPath)</div>@endif
    @if ($hasError)
        <p id="{{ $id }}-help" class="text-sm text-fg-danger">{{ $bagErrors->first($key) }}</p>
    @elseif ($helper)
        <p id="{{ $id }}-help" class="{{ $size === 'lg' ? 'type-body-xs' : 'text-sm' }} text-fg-secondary">{{ $helper }}</p>
    @endif
</div>
