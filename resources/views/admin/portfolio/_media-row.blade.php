<li data-repeater-item class="flex flex-wrap items-center gap-2 rounded-lg border border-line bg-canvas p-2">
    <select name="{{ $name }}[]" aria-label="{{ $label }}" class="min-w-0 flex-1 rounded-md border border-line bg-surface px-3 py-2 text-sm text-fg">
        <option value="">{{ __('No image') }}</option>
        @foreach ($options as $value => $optionLabel)
            <option value="{{ $value }}" @selected($selected === (int) $value)>{{ $optionLabel }}</option>
        @endforeach
    </select>
    <span class="flex gap-1" data-repeater-controls hidden>
        <button type="button" data-repeater-up class="rounded-md px-2.5 py-1 text-xs font-semibold text-fg-secondary hover:bg-subtle">{{ __('Move up') }}</button>
        <button type="button" data-repeater-down class="rounded-md px-2.5 py-1 text-xs font-semibold text-fg-secondary hover:bg-subtle">{{ __('Move down') }}</button>
        <button type="button" data-repeater-remove class="rounded-md px-2.5 py-1 text-xs font-semibold text-fg-danger hover:bg-danger-subtle">{{ __('Remove') }}</button>
    </span>
</li>
