<x-layouts.admin :title="$tag->exists ? __('Edit tag') : __('New tag')" :crumb="__('Blog')">
    @if ($errors->any())
        <x-ui.alert tone="danger" :title="__('Please fix the highlighted fields')">{{ __('Some fields need attention.') }}</x-ui.alert>
    @endif

    <div class="grid gap-6 xl:grid-cols-[1fr_340px]">
        <form id="tag-form" method="POST" action="{{ $tag->exists ? route('admin.tags.update', $tag) : route('admin.tags.store') }}" class="flex min-w-0 flex-col gap-6">
            @csrf
            @if ($tag->exists)
                @method('PUT')
            @endif

            @foreach (\App\Models\Language::active() as $code => $language)
                @php($translation = $tag->exists ? $tag->exactTranslation($code) : null)
                <x-ui.card :title="$language['native_name']" lang="{{ $code }}" dir="{{ $language['direction'] }}">
                    <div class="flex flex-col gap-4">
                        <x-ui.input :name="'translations['.$code.'][name]'" :id="'name-'.$code" :label="__('Name')" :value="$translation?->name" maxlength="100" />
                        <x-ui.input :name="'translations['.$code.'][slug]'" :id="'slug-'.$code" :label="__('URL slug')" :value="$translation?->slug" :helper="url($code.'/blog/tag').'/…'" />
                    </div>
                </x-ui.card>
            @endforeach
        </form>

        <div class="flex flex-col gap-6">
            <x-ui.card :title="__('Details')">
                <x-ui.button form="tag-form" class="w-full">{{ $tag->exists ? __('Save changes') : __('Create tag') }}</x-ui.button>
            </x-ui.card>

            @if ($tag->exists)
                <x-ui.card>
                    <form method="POST" action="{{ route('admin.tags.destroy', $tag) }}" data-confirm="{{ __('Delete this tag? It is removed from every article.') }}" data-confirm-label="{{ __('Delete') }}">
                        @csrf
                        @method('DELETE')
                        <x-ui.button variant="ghost" class="w-full text-fg-danger">{{ __('Delete tag') }}</x-ui.button>
                    </form>
                </x-ui.card>
            @endif
        </div>
    </div>
</x-layouts.admin>
