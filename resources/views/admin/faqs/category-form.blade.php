<x-layouts.admin :title="$category->exists ? __('Edit category') : __('New category')" :crumb="__('FAQ')">
    @if ($errors->any())
        <x-ui.alert tone="danger" :title="__('Please fix the highlighted fields')">{{ __('Some fields need attention.') }}</x-ui.alert>
    @endif

    <div class="grid gap-6 xl:grid-cols-[1fr_340px]">
        <form id="category-form" method="POST" action="{{ $category->exists ? route('admin.faq-categories.update', $category) : route('admin.faq-categories.store') }}" class="flex min-w-0 flex-col gap-6">
            @csrf
            @if ($category->exists)
                @method('PUT')
            @endif

            @foreach (\App\Models\Language::active() as $code => $language)
                @php($translation = $category->exists ? $category->exactTranslation($code) : null)
                <x-ui.card :title="$language['native_name']" lang="{{ $code }}" dir="{{ $language['direction'] }}">
                    <x-ui.input :name="'translations['.$code.'][name]'" :id="'name-'.$code" :label="__('Name')" :value="$translation?->name" maxlength="100" />
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
                @include('admin._trash-card', ['action' => route('admin.faq-categories.destroy', $category), 'confirm' => __('Delete this category? Its questions stay published without a category.'), 'label' => __('Delete category')])
            @endif
        </div>
    </div>
</x-layouts.admin>
