@props(['name', 'label', 'type' => 'text', 'value' => null, 'helper' => null, 'dir' => null])

@php
    $key = trim(str_replace(['[', ']'], ['.', ''], $name), '.');
    $id = $attributes->get('id', $key);
    $hasError = $errors->has($key);
@endphp

<div class="flex flex-col gap-1.5">
    <label for="{{ $id }}" class="text-sm font-semibold text-fg">{{ $label }}</label>
    <input
        id="{{ $id }}"
        name="{{ $name }}"
        type="{{ $type }}"
        value="{{ $type === 'password' ? '' : old($key, $value) }}"
        @if ($dir) dir="{{ $dir }}" @endif
        @if ($hasError || $helper) aria-describedby="{{ $id }}-help" @endif
        @if ($hasError) aria-invalid="true" @endif
        {{ $attributes->except('id')->merge(['class' => 'w-full rounded-md border bg-surface px-3 py-2.5 text-base text-fg placeholder:text-fg-tertiary focus:border-line-focus focus:outline-none focus:ring-4 focus:ring-brand/20 '.($hasError ? 'border-line-danger' : 'border-line')]) }}
    >
    @if ($hasError)
        <p id="{{ $id }}-help" class="text-sm text-fg-danger">{{ $errors->first($key) }}</p>
    @elseif ($helper)
        <p id="{{ $id }}-help" class="text-sm text-fg-secondary">{{ $helper }}</p>
    @endif
</div>
