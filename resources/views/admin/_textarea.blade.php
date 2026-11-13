{{-- Labelled textarea with old() value and inline error. $name uses bracket notation. --}}
@php($key = trim(str_replace(['[', ']'], ['.', ''], $name), '.'))
{{-- Requests may normalise list fields into arrays; show them one per line again after a validation error. --}}
@php($current = old($key, $value))
<div class="flex flex-col gap-1.5">
    <label for="{{ $id }}" class="text-sm font-semibold text-fg">{{ $label }}</label>
    <textarea id="{{ $id }}" name="{{ $name }}" rows="{{ $rows ?? 3 }}" @isset($max) maxlength="{{ $max }}" @endisset
              @if ($help ?? null) aria-describedby="{{ $id }}-help" @endif
              class="rounded-md border bg-surface px-3 py-2 text-sm text-fg {{ ($mono ?? false) ? 'font-mono' : '' }} {{ $errors->has($key) ? 'border-line-danger' : 'border-line-strong' }}">{{ is_array($current) ? implode("\n", $current) : $current }}</textarea>
    @error($key)<p class="text-sm text-fg-danger">{{ $message }}</p>@enderror
    @if ($help ?? null)
        <p id="{{ $id }}-help" class="text-xs text-fg-tertiary">{{ $help }}</p>
    @endif
</div>
