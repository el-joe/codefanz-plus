<x-layouts.admin :title="$category->exists ? __('Edit category') : __('New category')" :crumb="__('Blog')">
    @if ($errors->any())
        <x-ui.alert tone="danger" :title="__('Please fix the highlighted fields')">{{ $errors->first('category') ?: __('Some fields need attention.') }}</x-ui.alert>
    @endif

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
        <form id="category-form" method="POST" action="{{ $category->exists ? route('admin.post-categories.update', $category) : route('admin.post-categories.store') }}" class="flex min-w-0 flex-col gap-6">
            @csrf
            @if ($category->exists)
                @method('PUT')
            @endif

            @foreach (\App\Models\Language::active() as $code => $language)
                @php($translation = $category->exists ? $category->exactTranslation($code) : null)
                <x-ui.card :title="$language['native_name']" lang="{{ $code }}" dir="{{ $language['direction'] }}">
                    <div class="flex flex-col gap-4">
                        <x-ui.input :name="'translations['.$code.'][name]'" :id="'name-'.$code" :label="__('Name')" :value="$translation?->name" maxlength="100" />
                        <x-ui.input :name="'translations['.$code.'][slug]'" :id="'slug-'.$code" :label="__('URL slug')" :value="$translation?->slug" :helper="url($code.'/blog/category').'/…'" />
                        @include('admin._textarea', ['name' => "translations[{$code}][description]", 'id' => "description-{$code}", 'label' => __('Description'), 'value' => $translation?->description, 'rows' => 2, 'max' => 300])
                    </div>
                </x-ui.card>
            @endforeach
        </form>

        <div class="flex flex-col gap-6">
            <x-ui.card :title="__('Details')">
                <div class="flex flex-col gap-4">
                    <x-ui.input name="sort_order" form="category-form" type="number" min="0" max="65535" :label="__('Sort order')" :value="$category->sort_order ?? 0" :helper="__('Lower numbers appear first.')" />
                    <x-ui.button form="category-form">{{ $category->exists ? __('Save changes') : __('Create category') }}</x-ui.button>
                </div>
            </x-ui.card>

            @if ($category->exists)
                <x-ui.card>
                    <form method="POST" action="{{ route('admin.post-categories.destroy', $category) }}" data-confirm="{{ __('Delete this category? This cannot be undone.') }}" data-confirm-label="{{ __('Delete') }}">
                        @csrf
                        @method('DELETE')
                        <x-ui.button variant="ghost" class="w-full text-fg-danger">{{ __('Delete category') }}</x-ui.button>
                    </form>
                </x-ui.card>
            @endif
        </div>
    </div>
</x-layouts.admin>
