@php
    $purpose = $template->purpose;
    $isTranslation = $purpose === \App\Enums\PromptPurpose::Translation;
@endphp

<x-layouts.admin :title="__($template->name)" :crumb="__('AI Content Studio')">
    @include('admin.ai-studio._banner')

    @if ($errors->has('ai'))
        <x-ui.alert tone="danger" :title="__('Nothing was generated')" data-generation-error>{{ $errors->first('ai') }}</x-ui.alert>
    @endif

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
        <x-ui.card :title="__('Brief')">
            @if ($purpose->needsTarget())
                <form method="GET" action="{{ route('admin.ai-studio.create') }}" data-auto-submit class="mb-5 flex flex-wrap items-end gap-3">
                    <input type="hidden" name="template" value="{{ $template->key }}">
                    <div class="min-w-56 flex-1">
                        <x-ui.select name="target_type" id="target_type_picker" :label="__('Website item type')" :options="$targetTypes" :value="$targetType" :placeholder="__('Choose a type')" />
                    </div>
                    <noscript><x-ui.button size="sm" variant="secondary">{{ __('Show items') }}</x-ui.button></noscript>
                </form>
            @endif

            <form method="POST" action="{{ route('admin.ai-studio.generations.store') }}" class="flex flex-col gap-5" data-studio-form>
                @csrf
                <input type="hidden" name="template" value="{{ $template->key }}">

                @if ($purpose->needsTarget())
                    <input type="hidden" name="target_type" value="{{ $targetType }}">
                    @if ($targetType === null)
                        <p class="text-sm text-fg-secondary">{{ __('Choose the type of website item first.') }}</p>
                    @else
                        <x-ui.select name="target_id" :label="__('Website item')" :options="$targetItems->all()" :value="$selectedTargetId" :placeholder="__('Choose an item')" required />
                    @endif
                @endif

                @foreach ($template->declaredVariables() as $variable)
                    @if ($variable['max'] > 300)
                        @include('admin._textarea', ['name' => 'inputs['.$variable['name'].']', 'id' => 'input-'.$variable['name'], 'label' => __($variable['label']).($variable['required'] ? ' *' : ''), 'value' => null, 'rows' => 4, 'max' => $variable['max']])
                    @else
                        <x-ui.input :name="'inputs['.$variable['name'].']'" :id="'input-'.$variable['name']" :label="__($variable['label']).($variable['required'] ? ' *' : '')" :maxlength="$variable['max']" dir="auto" />
                    @endif
                @endforeach

                @if ($isTranslation)
                    <input type="hidden" name="locales[]" value="ar">
                    <p class="rounded-lg bg-subtle px-4 py-3 text-sm text-fg-secondary">{{ __('Translates the English content into Arabic and uses the glossary terms exactly.') }}</p>
                @else
                    <fieldset class="flex flex-col gap-2">
                        <legend class="mb-1 text-sm font-semibold text-fg">{{ __('Languages') }}</legend>
                        <div class="flex flex-wrap gap-4">
                            @foreach ($languages as $code => $language)
                                <label class="flex items-center gap-2 text-sm text-fg">
                                    <input type="checkbox" name="locales[]" value="{{ $code }}" @checked(in_array($code, old('locales', ['en']), true)) class="size-4 rounded border-line-strong">
                                    <span lang="{{ $code }}">{{ $language['native_name'] }}</span>
                                </label>
                            @endforeach
                        </div>
                        @error('locales')<p class="text-sm text-fg-danger">{{ $message }}</p>@enderror
                    </fieldset>
                    @if ($purpose->needsTarget())
                        <label class="flex items-start gap-2 text-sm text-fg">
                            <input type="checkbox" name="translate_from_en" value="1" @checked(old('translate_from_en')) class="mt-0.5 size-4 rounded border-line-strong">
                            <span>{{ __('Instead, translate the English version into Arabic keeping the glossary') }}</span>
                        </label>
                    @endif
                @endif

                <div class="flex flex-wrap items-center gap-3">
                    <x-ui.button :disabled="$reason !== null" data-generate-button>{{ __('Generate draft') }}</x-ui.button>
                    <span class="text-xs text-fg-tertiary">{{ __('The draft stays private. Nothing goes live without a human approval.') }}</span>
                </div>
            </form>
        </x-ui.card>

        <div class="flex flex-col gap-4">
            <x-ui.card :title="__('How it works')">
                <ol class="flex list-decimal flex-col gap-2 ps-5 text-sm text-fg-secondary">
                    <li>{{ __('The AI writes from your brief, the brand voice and the glossary.') }}</li>
                    <li>{{ __('Automatic checks flag banned claims, unverified numbers, prices and unknown links.') }}</li>
                    <li>{{ __('You compare it with the live content, edit it and decide what to do with it.') }}</li>
                    <li>
                        @if ($purpose === \App\Enums\PromptPurpose::BlogDraft)
                            {{ __('A blog draft goes through the editorial workflow; someone else must approve it.') }}
                        @elseif ($purpose->needsTarget())
                            {{ __('Changes to a live item go to the approval queue and are never applied directly.') }}
                        @else
                            {{ __('The result is kept as a private draft in your generation history.') }}
                        @endif
                    </li>
                </ol>
            </x-ui.card>
            <x-ui.card :title="__('Template')">
                <dl class="flex flex-col gap-2 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-fg-secondary">{{ __('Purpose') }}</dt><dd class="text-fg">{{ $purpose->label() }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-fg-secondary">{{ __('Version') }}</dt><dd class="font-mono text-fg" dir="ltr">v{{ $template->version }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-fg-secondary">{{ __('Metered as') }}</dt><dd class="text-fg">{{ $purpose->feature()->label() }}</dd></div>
                </dl>
            </x-ui.card>
        </div>
    </div>
</x-layouts.admin>
