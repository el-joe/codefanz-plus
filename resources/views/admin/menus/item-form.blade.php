@php
    $type = old('link_type', $item->link_type?->value ?? 'route');
    $linkable = old('linkable', $item->linkable_type ? $item->linkable_type.':'.$item->linkable_id : null);
    $routeQuery = old('route_query', $item->route_params ? http_build_query($item->route_params) : null);
    $action = $item->exists ? route('admin.menus.items.update', [$menu, $item]) : route('admin.menus.items.store', $menu);
@endphp

<x-layouts.admin :title="$item->exists ? __('Edit menu item') : __('New menu item')" :crumb="$menu->location->label()">
    @if ($errors->any())
        <x-ui.alert tone="danger" :title="__('Please fix the highlighted fields')">{{ __('Some fields need attention.') }}</x-ui.alert>
    @endif

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
        <form id="item-form" method="POST" action="{{ $action }}" class="flex min-w-0 flex-col gap-6">
            @csrf
            @if ($item->exists)
                @method('PUT')
            @endif

            @foreach (\App\Models\Language::active() as $code => $language)
                @php($translation = $item->exists ? $item->exactTranslation($code) : null)
                <x-ui.card :title="$language['native_name']" lang="{{ $code }}" dir="{{ $language['direction'] }}">
                    <x-ui.input :name="'translations['.$code.'][label]'" :id="'label-'.$code" :label="__('Label')" :value="$translation?->label" maxlength="100" />
                </x-ui.card>
            @endforeach

            <x-ui.card :title="__('Link')">
                <div class="flex flex-col gap-5">
                    <fieldset class="flex flex-col gap-2">
                        <legend class="mb-1 text-sm font-semibold text-fg">{{ __('Links to') }}</legend>
                        @foreach (\App\Enums\MenuLinkType::options() as $value => $label)
                            <label class="flex items-center gap-2 text-sm text-fg">
                                <input type="radio" name="link_type" value="{{ $value }}" @checked($type === $value) class="size-4 accent-brand">
                                {{ $label }}
                            </label>
                        @endforeach
                        @error('link_type')<p class="text-sm text-fg-danger">{{ $message }}</p>@enderror
                    </fieldset>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <x-ui.select name="route_name" :label="__('Site section')" :value="$item->route_name" :placeholder="__('Choose a section')"
                                     :options="collect(\App\Models\MenuItem::ROUTES)->map(fn ($label) => __($label))->all()" />
                        <x-ui.input name="route_query" :label="__('Filter (optional)')" :value="$routeQuery" dir="ltr" placeholder="category=saas" :helper="__('Query parameters, e.g. category=saas.')" />
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label for="linkable" class="text-sm font-semibold text-fg">{{ __('Content item') }}</label>
                        <select id="linkable" name="linkable" @error('linkable') aria-invalid="true" @enderror class="w-full rounded-md border border-line-strong bg-surface px-3 py-2.5 text-base text-fg">
                            <option value="">{{ __('Choose content') }}</option>
                            @foreach ($linkables as $group => $options)
                                <optgroup label="{{ $group }}">
                                    @foreach ($options as $value => $label)
                                        <option value="{{ $value }}" @selected($linkable === $value)>{{ $label }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                        @error('linkable')<p class="text-sm text-fg-danger">{{ $message }}</p>@enderror
                        <p class="text-sm text-fg-secondary">{{ __('Each language links to that language’s page. Hidden when the content is unpublished or not translated.') }}</p>
                    </div>

                    <x-ui.input name="url" type="url" :label="__('External link')" :value="$item->url" dir="ltr" placeholder="https://" :helper="__('Full https:// address.')" />
                </div>
            </x-ui.card>
        </form>

        <div class="flex flex-col gap-6">
            <x-ui.card :title="__('Settings')">
                <div class="flex flex-col gap-4">
                    @if ($parents->isNotEmpty() || $item->parent_id)
                        <x-ui.select name="parent_id" form="item-form" :label="__('Parent item')" :value="$item->parent_id" :placeholder="__('Top level')"
                                     :options="$parents->mapWithKeys(fn ($parent) => [$parent->id => $parent->translation()?->label ?? '#'.$parent->id])->all()"
                                     :helper="__('Sub-items appear in the parent’s dropdown.')" />
                    @endif
                    <x-ui.input name="sort_order" form="item-form" type="number" min="0" max="65535" :label="__('Sort order')" :value="$item->sort_order ?? 0" :helper="__('Lower numbers appear first.')" />
                    <label class="flex items-center justify-between gap-3">
                        <span class="text-sm font-semibold text-fg">{{ __('Visible') }}</span>
                        <input type="checkbox" name="is_visible" value="1" form="item-form" @checked(old('is_visible', $item->is_visible)) class="size-4 accent-brand">
                    </label>
                    <label class="flex items-center justify-between gap-3">
                        <span class="text-sm font-semibold text-fg">{{ __('Open in a new tab') }}</span>
                        <input type="checkbox" name="open_in_new_tab" value="1" form="item-form" @checked(old('open_in_new_tab', $item->open_in_new_tab)) class="size-4 accent-brand">
                    </label>
                    <x-ui.button form="item-form">{{ $item->exists ? __('Save changes') : __('Add item') }}</x-ui.button>
                </div>
            </x-ui.card>

            @if ($item->exists)
                @include('admin._trash-card', ['action' => route('admin.menus.items.destroy', [$menu, $item]), 'confirm' => __('Delete this menu item and its sub-items? This cannot be undone.'), 'label' => __('Delete item')])
            @endif
        </div>
    </div>
</x-layouts.admin>
