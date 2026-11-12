<x-layouts.admin :title="$technology->exists ? __('Edit technology') : __('New technology')" :crumb="__('Technologies')">
    @if ($errors->any())
        <x-ui.alert tone="danger" :title="__('Please fix the highlighted fields')">{{ __('Some fields need attention.') }}</x-ui.alert>
    @endif

    <div class="grid gap-6 xl:grid-cols-[1fr_340px]">
        <form id="technology-form" method="POST" action="{{ $technology->exists ? route('admin.technologies.update', $technology) : route('admin.technologies.store') }}" class="flex min-w-0 flex-col gap-6">
            @csrf
            @if ($technology->exists)
                @method('PUT')
            @endif

            @foreach (\App\Models\Language::active() as $code => $language)
                @php($translation = $technology->exists ? $technology->exactTranslation($code) : null)
                <x-ui.card :title="$language['native_name']" lang="{{ $code }}" dir="{{ $language['direction'] }}">
                    <div class="flex flex-col gap-4">
                        <x-ui.input :name="'translations['.$code.'][name]'" :id="'name-'.$code" :label="__('Name')" :value="$translation?->name" maxlength="100" />
                        <x-ui.input :name="'translations['.$code.'][description]'" :id="'description-'.$code" :label="__('Short description')" :value="$translation?->description" maxlength="500" />
                    </div>
                </x-ui.card>
            @endforeach

            <x-ui.card :title="__('Details')">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.select name="technology_category_id" :label="__('Category')" :value="$technology->technology_category_id" :options="$categories" />
                    <x-ui.input name="key" :label="__('Key')" :value="$technology->key" maxlength="60" dir="ltr" :helper="__('Unique identifier, e.g. laravel.')" />
                    <x-ui.input name="icon" :label="__('Icon key')" :value="$technology->icon" maxlength="60" dir="ltr" :helper="__('Optional brand icon identifier.')" />
                    <x-ui.input name="website_url" type="url" :label="__('Official website')" :value="$technology->website_url" dir="ltr" />
                </div>
            </x-ui.card>
        </form>

        <div class="flex flex-col gap-6">
            <x-ui.card :title="__('Proficiency')">
                <fieldset class="flex flex-col gap-3">
                    <legend class="sr-only">{{ __('Proficiency') }}</legend>
                    @foreach (\App\Enums\TechnologyProficiency::assignable() as $level)
                        <label class="flex items-start gap-3 rounded-md border border-line p-3 has-checked:border-line-brand has-checked:bg-brand-subtle">
                            <input type="radio" name="proficiency" value="{{ $level->value }}" form="technology-form" @checked(old('proficiency', $technology->proficiency?->value ?? 'supported') === $level->value) class="mt-0.5 size-4 accent-brand">
                            <span class="flex flex-col">
                                <span class="text-sm font-semibold text-fg">{{ $level->label() }}</span>
                                <span class="text-xs text-fg-tertiary">{{ $level->description() }}</span>
                            </span>
                        </label>
                    @endforeach
                    @error('proficiency')<p class="text-sm text-fg-danger">{{ $message }}</p>@enderror
                    <p class="text-xs text-fg-tertiary">
                        @if ($technology->exists && $technology->isVerifiedInProjects())
                            {{ __('Shown publicly as Verified because a published project uses it.') }}
                        @else
                            {{ __('Becomes Verified automatically once a published portfolio project uses it.') }}
                        @endif
                    </p>
                </fieldset>
            </x-ui.card>

            <x-ui.card :title="__('Publishing')">
                <div class="flex flex-col gap-4">
                    <x-ui.input name="sort_order" form="technology-form" type="number" min="0" max="65535" :label="__('Sort order')" :value="$technology->sort_order ?? 0" />
                    <label class="flex items-center justify-between gap-3">
                        <span class="text-sm font-semibold text-fg">{{ __('Visible on the website') }}</span>
                        <input type="checkbox" name="is_visible" value="1" form="technology-form" @checked(old('is_visible', $technology->exists ? $technology->is_visible : true)) class="size-4 accent-brand">
                    </label>
                    <x-ui.button form="technology-form">{{ $technology->exists ? __('Save changes') : __('Create technology') }}</x-ui.button>
                </div>
            </x-ui.card>

            @if ($technology->exists)
                <x-ui.card>
                    <form method="POST" action="{{ route('admin.technologies.destroy', $technology) }}" data-confirm="{{ __('Move this technology to trash? It will no longer be visible on the site.') }}" data-confirm-label="{{ __('Move to trash') }}">
                        @csrf
                        @method('DELETE')
                        <x-ui.button variant="ghost" class="w-full text-fg-danger">{{ __('Move to trash') }}</x-ui.button>
                    </form>
                </x-ui.card>
            @endif
        </div>
    </div>
</x-layouts.admin>
