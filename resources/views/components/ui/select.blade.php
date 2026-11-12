@props(['name', 'label', 'options' => [], 'value' => null, 'helper' => null, 'placeholder' => null, 'bag' => 'default'])

@php
    $key = trim(str_replace(['[', ']'], ['.', ''], $name), '.');
    $id = $attributes->get('id', $key);
    $bagErrors = $errors->getBag($bag);
    $hasError = $bagErrors->has($key);
    $selected = (string) old($key, $value);
@endphp

<div class="flex flex-col gap-1.5">
    <label for="{{ $id }}" class="text-sm font-semibold text-fg">{{ $label }}</label>
    <select
        id="{{ $id }}"
        name="{{ $name }}"
        @if ($hasError || $helper) aria-describedby="{{ $id }}-help" @endif
        @if ($hasError) aria-invalid="true" @endif
        {{ $attributes->except('id')->merge(['class' => 'w-full rounded-md border bg-surface px-3 py-2.5 text-base text-fg focus:border-line-focus focus:outline-none focus:ring-4 focus:ring-brand/20 '.($hasError ? 'border-line-danger' : 'border-line')]) }}
    >
        @if ($placeholder !== null)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected($selected === (string) $optionValue)>{{ $optionLabel }}</option>
        @endforeach
    </select>
    @if ($hasError)
        <p id="{{ $id }}-help" class="text-sm text-fg-danger">{{ $bagErrors->first($key) }}</p>
    @elseif ($helper)
        <p id="{{ $id }}-help" class="text-sm text-fg-secondary">{{ $helper }}</p>
    @endif
</div>
