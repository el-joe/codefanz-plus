{{-- One ordered child row (feature or FAQ) of the service form. $index may be the literal __INDEX__ inside the JS template. --}}

<li data-repeater-item class="flex flex-col gap-3 rounded-lg border border-line bg-canvas p-4">
    <input type="hidden" name="{{ $group }}[{{ $index }}][id]" value="{{ $row['id'] ?? '' }}">
    <div class="grid gap-4 lg:grid-cols-2">
        @foreach (\App\Models\Language::active() as $code => $language)
            <div class="flex flex-col gap-2" lang="{{ $code }}" dir="{{ $language['direction'] }}">
                <p class="text-xs font-semibold uppercase text-fg-tertiary">{{ $language['native_name'] }}</p>
                @foreach ($fields as $field => $config)
                    @php($key = "{$group}.{$index}.translations.{$code}.{$field}")
                    @php($inputId = "{$group}-{$index}-{$code}-{$field}")
                    <label for="{{ $inputId }}" class="sr-only">{{ $config['label'] }} ({{ $language['native_name'] }})</label>
                    @if ($config['multiline'])
                        <textarea id="{{ $inputId }}" name="{{ $group }}[{{ $index }}][translations][{{ $code }}][{{ $field }}]" rows="{{ $config['rows'] ?? 2 }}" maxlength="{{ $config['max'] }}" placeholder="{{ $config['label'] }}"
                                  class="rounded-md border border-line bg-surface px-3 py-2 text-sm text-fg placeholder:text-fg-tertiary">{{ $row['translations'][$code][$field] ?? '' }}</textarea>
                    @else
                        <input id="{{ $inputId }}" type="text" name="{{ $group }}[{{ $index }}][translations][{{ $code }}][{{ $field }}]" value="{{ $row['translations'][$code][$field] ?? '' }}" maxlength="{{ $config['max'] }}" placeholder="{{ $config['label'] }}"
                               class="rounded-md border border-line bg-surface px-3 py-2 text-sm text-fg placeholder:text-fg-tertiary">
                    @endif
                    @error($key)<p class="text-sm text-fg-danger">{{ $message }}</p>@enderror
                @endforeach
            </div>
        @endforeach
    </div>
    <div class="flex flex-wrap justify-end gap-2" data-repeater-controls hidden>
        <button type="button" data-repeater-up class="rounded-md px-2.5 py-1 text-xs font-semibold text-fg-secondary hover:bg-subtle">{{ __('Move up') }}</button>
        <button type="button" data-repeater-down class="rounded-md px-2.5 py-1 text-xs font-semibold text-fg-secondary hover:bg-subtle">{{ __('Move down') }}</button>
        <button type="button" data-repeater-remove class="rounded-md px-2.5 py-1 text-xs font-semibold text-fg-danger hover:bg-danger-subtle">{{ __('Remove') }}</button>
    </div>
</li>
