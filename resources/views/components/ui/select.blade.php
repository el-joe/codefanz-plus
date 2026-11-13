{{-- Figma "Select": same API as x-ui.input. size md (admin) | lg (48px public forms with the Figma chevron). --}}
@props(['name', 'label', 'options' => [], 'value' => null, 'helper' => null, 'placeholder' => null, 'bag' => 'default', 'size' => 'md'])

@php
    $key = trim(str_replace(['[', ']'], ['.', ''], $name), '.');
    $id = $attributes->get('id', $key);
    $bagErrors = $errors->getBag($bag);
    $hasError = $bagErrors->has($key);
    $selected = (string) old($key, $value);
    $large = $size === 'lg';
@endphp

<div class="flex flex-col gap-1.5">
    <label for="{{ $id }}" class="{{ $large ? 'type-label-md' : 'text-sm font-semibold' }} text-fg">{{ $label }}</label>
    @if ($large)<div class="relative">@endif
    <select
        id="{{ $id }}"
        name="{{ $name }}"
        @if ($hasError || $helper) aria-describedby="{{ $id }}-help" @endif
        @if ($hasError) aria-invalid="true" @endif
        {{ $attributes->except('id')->merge(['class' => 'w-full rounded-md border bg-surface '.($large ? 'h-12 appearance-none ps-3 pe-10 type-body-md' : 'px-3 py-2.5 text-base').' text-fg focus:border-line-focus focus:outline-none focus:ring-4 focus:ring-brand/20 '.($hasError ? 'border-line-danger' : 'border-line')]) }}
    >
        @if ($placeholder !== null)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected($selected === (string) $optionValue)>{{ $optionLabel }}</option>
        @endforeach
    </select>
    @if ($large)
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="pointer-events-none absolute end-3 top-1/2 -translate-y-1/2 text-icon-secondary"><path d="m6 9 6 6 6-6"/></svg>
        </div>
    @endif
    @if ($hasError)
        <p id="{{ $id }}-help" class="text-sm text-fg-danger">{{ $bagErrors->first($key) }}</p>
    @elseif ($helper)
        <p id="{{ $id }}-help" class="{{ $large ? 'type-body-xs' : 'text-sm' }} text-fg-secondary">{{ $helper }}</p>
    @endif
</div>
