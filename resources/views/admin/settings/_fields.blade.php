{{-- Settings inputs for a set of fields. Expects $fields, $values, $masked, $languages. --}}
@foreach ($fields as $key => $field)
    @if ($field['type'] === 'translatable')
        <fieldset class="flex flex-col gap-3">
            <legend class="mb-1 text-sm font-semibold text-fg">{{ __($field['label']) }}</legend>
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ($languages as $code => $language)
                    <div lang="{{ $code }}" dir="{{ $language['direction'] }}">
                        <x-ui.input :name="'settings['.$key.']['.$code.']'" :id="$key.'-'.$code" :label="$language['native_name']" :value="$values[$key][$code] ?? null" />
                    </div>
                @endforeach
            </div>
        </fieldset>
    @elseif ($field['type'] === 'secret')
        <div class="flex flex-col gap-2">
            <x-ui.input :name="'settings['.$key.']'" :id="$key" type="password" :label="__($field['label'])" autocomplete="new-password" dir="ltr"
                        :helper="$masked[$key] ? __('Saved as :value. Leave blank to keep it.', ['value' => $masked[$key]]) : __('Encrypted at rest and never shown again after saving.')" />
            @if ($masked[$key])
                <label class="flex items-center gap-2 text-sm text-fg-secondary">
                    <input type="checkbox" name="clear[]" value="{{ $key }}" class="size-4 accent-brand">
                    {{ __('Remove the saved value') }}
                </label>
            @endif
        </div>
    @elseif ($field['type'] === 'select')
        <x-ui.select :name="'settings['.$key.']'" :id="$key" :label="__($field['label'])" :options="array_map(fn ($option) => __($option), $field['options'])" :value="$values[$key] ?? null" :placeholder="__('Default')" />
    @elseif ($field['type'] === 'number')
        <x-ui.input :name="'settings['.$key.']'" :id="$key" type="number" step="0.01" min="0" :label="__($field['label'])" :value="$values[$key] ?? null" dir="ltr" />
    @else
        <x-ui.input :name="'settings['.$key.']'" :id="$key" :type="$field['type'] === 'email' ? 'email' : 'text'" :label="__($field['label'])" :value="$values[$key] ?? null" dir="ltr" />
    @endif
@endforeach
