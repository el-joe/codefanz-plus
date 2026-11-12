@php
    $groupLabels = ['general' => __('General'), 'contact' => __('Contact'), 'integrations' => __('Integrations')];
    $languages = \App\Models\Language::active();
@endphp

<x-layouts.admin :title="__('Settings')" :crumb="__('System')">
    <nav class="flex flex-wrap gap-2" aria-label="{{ __('Settings sections') }}">
        @foreach ($groups as $name)
            <a href="{{ route('admin.settings.edit', $name) }}" @if ($group === $name) aria-current="page" @endif
               class="rounded-md px-3 py-1.5 text-sm font-semibold {{ $group === $name ? 'bg-surface text-fg shadow-xs ring-1 ring-line' : 'text-fg-secondary hover:bg-subtle' }}">{{ $groupLabels[$name] ?? \Illuminate\Support\Str::headline($name) }}</a>
        @endforeach
    </nav>

    @if ($errors->any())
        <x-ui.alert tone="danger" :title="__('Please fix the highlighted fields')">{{ __('Some fields need attention.') }}</x-ui.alert>
    @endif

    @if ($integrations !== [])
        <div class="grid gap-4 md:grid-cols-2">
            @foreach ($integrations as $integration)
                <x-ui.card>
                    <div class="flex items-center justify-between gap-3">
                        <p class="font-semibold text-fg" dir="auto">{{ __($integration['label']) }}</p>
                        @if ($integration['configured'])
                            <x-ui.badge tone="info">{{ __('Credentials saved · not verified') }}</x-ui.badge>
                        @elseif ($integration['partial'])
                            <x-ui.badge tone="warning">{{ __('Incomplete') }}</x-ui.badge>
                        @else
                            <x-ui.badge>{{ __('Requires credentials') }}</x-ui.badge>
                        @endif
                    </div>
                    @if ($integration['selected'])
                        <p class="mt-2 text-sm font-semibold text-fg-brand" data-ai-provider-selected>{{ __('Selected AI provider') }}{{ $integration['configured'] ? '' : ' · '.__('the assistant uses its guided questionnaire until a key is saved') }}</p>
                    @endif
                    <p class="mt-2 text-xs text-fg-tertiary">{{ __('Connection tests arrive with each integration. Until then a status never claims to be live.') }}</p>
                </x-ui.card>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('admin.settings.update', $group) }}" class="flex max-w-3xl flex-col gap-6">
        @csrf
        @method('PUT')

        <x-ui.card>
            <div class="flex flex-col gap-5">
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
                        <x-ui.select :name="'settings['.$key.']'" :id="$key" :label="__($field['label'])" :options="$field['options']" :value="$values[$key] ?? null" :placeholder="__('Default')" />
                    @elseif ($field['type'] === 'number')
                        <x-ui.input :name="'settings['.$key.']'" :id="$key" type="number" step="0.01" min="0" :label="__($field['label'])" :value="$values[$key] ?? null" dir="ltr" />
                    @else
                        <x-ui.input :name="'settings['.$key.']'" :id="$key" :type="$field['type'] === 'email' ? 'email' : 'text'" :label="__($field['label'])" :value="$values[$key] ?? null" dir="ltr" />
                    @endif
                @endforeach
            </div>
        </x-ui.card>

        <div><x-ui.button>{{ __('Save changes') }}</x-ui.button></div>
    </form>
</x-layouts.admin>
