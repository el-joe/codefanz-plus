@php
    $variables = old('variables', $template->variables ?? []);
    $rows = max(count($variables) + 2, 4);
    $placeholder = fn (string $name): string => '{'.'{'.$name.'}'.'}';
@endphp

<x-layouts.admin :title="$template->exists ? __($template->name) : __('New prompt')" :crumb="__('Prompt library')">
    @if ($errors->any())
        <x-ui.alert tone="danger" :title="__('Please fix the highlighted fields')">{{ $errors->first() }}</x-ui.alert>
    @endif

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
        <form method="POST" action="{{ $template->exists ? route('admin.prompts.update', $template) : route('admin.prompts.store') }}" class="flex flex-col gap-6">
            @csrf
            @if ($template->exists)
                @method('PUT')
                <input type="hidden" name="lock_version" value="{{ $template->version }}">
            @endif

            <x-ui.card>
                <div class="grid gap-4 md:grid-cols-2">
                    <x-ui.input name="name" :label="__('Name')" :value="$template->name" required maxlength="120" dir="auto" />
                    <x-ui.input name="key" :label="__('Key')" :value="$template->key" required maxlength="60" dir="ltr" :helper="__('Lowercase letters, digits and dashes, e.g. blog-article.')" />
                    <x-ui.select name="purpose" :label="__('Purpose')" :options="$purposes" :value="$template->purpose?->value" required />
                    <x-ui.select name="locale" :label="__('Language')" :options="$languages->map(fn ($language) => $language['native_name'])->all()" :value="$template->locale" :placeholder="__('Any language')" />
                    <div class="md:col-span-2"><x-ui.input name="description" :label="__('Description')" :value="$template->description" maxlength="255" dir="auto" /></div>
                    <label class="flex items-center gap-2 text-sm text-fg">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $template->is_active)) class="size-4 rounded border-line-strong">
                        {{ __('Active (shown in the studio)') }}
                    </label>
                </div>
            </x-ui.card>

            <x-ui.card :title="__('Prompts')">
                <div class="flex flex-col gap-4">
                    @include('admin._textarea', ['name' => 'system_template', 'id' => 'system_template', 'label' => __('System prompt'), 'value' => $template->system_template, 'rows' => 10, 'mono' => true, 'max' => 8000, 'help' => __('Built-in variables only: :names. Staff input never reaches the system prompt; fixed safety rules are always appended.', ['names' => collect(\App\Models\PromptTemplate::SYSTEM_VARIABLES)->map($placeholder)->implode(' ')])])
                    @include('admin._textarea', ['name' => 'user_template', 'id' => 'user_template', 'label' => __('User prompt'), 'value' => $template->user_template, 'rows' => 8, 'mono' => true, 'max' => 8000, 'help' => __('Use the variables declared below as :example, plus :language and :languages. Values are passed as quoted data, never as instructions.', ['example' => $placeholder('name'), 'language' => $placeholder('language'), 'languages' => $placeholder('languages')])])
                </div>
            </x-ui.card>

            <x-ui.card :title="__('Variables')">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm" data-variables>
                        <thead class="text-xs text-fg-tertiary">
                            <tr>
                                <th scope="col" class="pb-2 text-start font-semibold">{{ __('Name') }}</th>
                                <th scope="col" class="pb-2 text-start font-semibold">{{ __('Label') }}</th>
                                <th scope="col" class="pb-2 text-start font-semibold">{{ __('Max length') }}</th>
                                <th scope="col" class="pb-2 text-start font-semibold">{{ __('Required') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @for ($i = 0; $i < $rows; $i++)
                                <tr>
                                    <td class="py-1 pe-2"><input name="variables[{{ $i }}][name]" value="{{ $variables[$i]['name'] ?? '' }}" aria-label="{{ __('Name') }}" maxlength="40" dir="ltr" class="w-full rounded-md border {{ $errors->has("variables.{$i}.name") ? 'border-line-danger' : 'border-line' }} bg-surface px-2 py-1.5 font-mono text-sm text-fg">
                                        @error("variables.{$i}.name")<p class="text-xs text-fg-danger">{{ $message }}</p>@enderror</td>
                                    <td class="py-1 pe-2"><input name="variables[{{ $i }}][label]" value="{{ $variables[$i]['label'] ?? '' }}" aria-label="{{ __('Label') }}" maxlength="120" dir="auto" class="w-full rounded-md border border-line bg-surface px-2 py-1.5 text-sm text-fg"></td>
                                    <td class="py-1 pe-2"><input type="number" name="variables[{{ $i }}][max]" value="{{ $variables[$i]['max'] ?? 2000 }}" min="1" max="20000" aria-label="{{ __('Max length') }}" class="w-28 rounded-md border border-line bg-surface px-2 py-1.5 text-sm text-fg"></td>
                                    <td class="py-1"><input type="checkbox" name="variables[{{ $i }}][required]" value="1" @checked($variables[$i]['required'] ?? false) aria-label="{{ __('Required') }}" class="size-4 rounded border-line-strong"></td>
                                </tr>
                            @endfor
                        </tbody>
                    </table>
                </div>
                <p class="mt-2 text-xs text-fg-tertiary">{{ __('Leave the name empty to remove a row. Save to get more empty rows.') }}</p>
            </x-ui.card>

            <div><x-ui.button>{{ __('Save prompt') }}</x-ui.button></div>
        </form>

        <div class="flex flex-col gap-6">
            @if ($template->exists)
                <x-ui.card :title="__('Test run')">
                    <p class="mb-4 text-sm text-fg-secondary">{{ __('Runs the saved version against the configured provider with sample inputs. It is logged as usage and can never be published.') }}</p>
                    @if ($reason ?? null)
                        <x-ui.alert tone="warning" :title="__('AI generation is unavailable')">{{ $reason->label() }}</x-ui.alert>
                    @endif
                    <form method="POST" action="{{ route('admin.prompts.test', $template) }}" class="mt-2 flex flex-col gap-3" data-prompt-test>
                        @csrf
                        @foreach ($template->declaredVariables() as $variable)
                            <x-ui.input :name="'inputs['.$variable['name'].']'" :id="'test-'.$variable['name']" :label="__($variable['label'])" :maxlength="$variable['max']" dir="auto" />
                        @endforeach
                        <x-ui.select name="test_locale" :label="__('Language')" :options="$languages->map(fn ($language) => $language['native_name'])->all()" :value="$template->locale ?? 'en'" />
                        <x-ui.button variant="secondary" :disabled="($reason ?? null) !== null">{{ __('Run test') }}</x-ui.button>
                    </form>
                    @foreach ($dryRuns as $run)
                        <a href="{{ route('admin.ai-studio.generations.show', $run) }}" class="mt-2 block text-xs text-fg-brand hover:underline">{{ __('Test run :date', ['date' => $run->created_at->format('Y-m-d H:i')]) }} · v{{ $run->template_version }}</a>
                    @endforeach
                </x-ui.card>

                <x-ui.card :title="__('Version history')">
                    <ol class="flex flex-col gap-2 text-sm" data-version-history>
                        @foreach ($versions as $version)
                            <li class="flex justify-between gap-3">
                                <span class="font-mono text-fg" dir="ltr">v{{ $version->version }}</span>
                                <span class="text-fg-secondary">{{ $version->creator?->name ?? __('System') }} · {{ $version->created_at?->format('Y-m-d H:i') }}</span>
                            </li>
                        @endforeach
                    </ol>
                </x-ui.card>
            @endif
        </div>
    </div>
</x-layouts.admin>
