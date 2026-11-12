<x-layouts.admin :title="$category->exists ? __('Edit category') : __('New category')" :crumb="__('Technologies')">
    @if ($errors->any())
        <x-ui.alert tone="danger" :title="__('Please fix the highlighted fields')">{{ $errors->first('category') ?: __('Some fields need attention.') }}</x-ui.alert>
    @endif

    <div class="grid gap-6 xl:grid-cols-[1fr_340px]">
        <form id="category-form" method="POST" action="{{ $category->exists ? route('admin.technology-categories.update', $category) : route('admin.technology-categories.store') }}" class="flex min-w-0 flex-col gap-6">
            @csrf
            @if ($category->exists)
                @method('PUT')
            @endif

            @foreach (\App\Models\Language::active() as $code => $language)
                <x-ui.card :title="$language['native_name']" lang="{{ $code }}" dir="{{ $language['direction'] }}">
                    <x-ui.input :name="'translations['.$code.'][name]'" :id="'name-'.$code" :label="__('Name')" :value="$category->exists ? $category->exactTranslation($code)?->name : null" maxlength="100" />
                </x-ui.card>
            @endforeach
        </form>

        <div class="flex flex-col gap-6">
            <x-ui.card :title="__('Details')">
                <div class="flex flex-col gap-4">
                    <x-ui.input name="key" form="category-form" :label="__('Key')" :value="$category->key" maxlength="60" dir="ltr" :helper="__('Unique identifier, e.g. frameworks.')" />
                    <x-ui.input name="sort_order" form="category-form" type="number" min="0" max="65535" :label="__('Sort order')" :value="$category->sort_order ?? 0" />
                    <x-ui.button form="category-form">{{ $category->exists ? __('Save changes') : __('Create category') }}</x-ui.button>
                </div>
            </x-ui.card>

            @if ($category->exists)
                <x-ui.card>
                    <form method="POST" action="{{ route('admin.technology-categories.destroy', $category) }}" data-confirm="{{ __('Delete this category? This cannot be undone.') }}" data-confirm-label="{{ __('Delete') }}">
                        @csrf
                        @method('DELETE')
                        <x-ui.button variant="ghost" class="w-full text-fg-danger">{{ __('Delete category') }}</x-ui.button>
                    </form>
                </x-ui.card>
            @endif
        </div>
    </div>
</x-layouts.admin>
