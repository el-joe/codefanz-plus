<x-layouts.admin :title="$faq->exists ? __('Edit question') : __('New question')" :crumb="__('FAQ')">
    @if ($errors->any())
        <x-ui.alert tone="danger" :title="__('Please fix the highlighted fields')">{{ $errors->first('status') ?: __('Some fields need attention.') }}</x-ui.alert>
    @endif

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
        <form id="faq-form" method="POST" action="{{ $faq->exists ? route('admin.faqs.update', $faq) : route('admin.faqs.store') }}" class="flex min-w-0 flex-col gap-6">
            @csrf
            @if ($faq->exists)
                @method('PUT')
            @endif

            @foreach (\App\Models\Language::active() as $code => $language)
                @php($translation = $faq->exists ? $faq->exactTranslation($code) : null)
                <x-ui.card :title="$language['native_name']" lang="{{ $code }}" dir="{{ $language['direction'] }}">
                    <div class="flex flex-col gap-4">
                        <x-ui.input :name="'translations['.$code.'][question]'" :id="'question-'.$code" :label="__('Question')" :value="$translation?->question" maxlength="255" />
                        @include('admin._textarea', ['name' => "translations[{$code}][answer]", 'id' => "answer-{$code}", 'label' => __('Answer (Markdown)'), 'value' => $translation?->answer, 'rows' => 6, 'mono' => true,
                            'help' => __('Headings, lists and links are supported. Raw HTML is removed for security.')])
                    </div>
                </x-ui.card>
            @endforeach
        </form>

        <div class="flex flex-col gap-6">
            @include('admin._publish-card', ['model' => $faq, 'form' => 'faq-form', 'publishUrl' => $faq->exists ? route('admin.faqs.publish', $faq) : null, 'liveUrl' => route('faq.index', ['locale' => app()->getLocale()])])

            <x-ui.card :title="__('Details')">
                <div class="flex flex-col gap-4">
                    <x-ui.select name="faq_category_id" form="faq-form" :label="__('Category')" :value="$faq->faq_category_id" :placeholder="__('No category')"
                                 :options="$categories->mapWithKeys(fn ($category) => [$category->id => $category->translation()?->name ?? '#'.$category->id])->all()" />
                    <x-ui.input name="sort_order" form="faq-form" type="number" min="0" max="65535" :label="__('Sort order')" :value="$faq->sort_order ?? 0" :helper="__('Lower numbers appear first.')" />
                    <label class="flex items-center justify-between gap-3">
                        <span class="text-sm font-semibold text-fg">{{ __('Feature on the homepage') }}</span>
                        <input type="checkbox" name="is_featured" value="1" form="faq-form" @checked(old('is_featured', $faq->is_featured)) class="size-4 accent-brand">
                    </label>
                </div>
            </x-ui.card>

            @if ($faq->exists)
                @include('admin._trash-card', ['action' => route('admin.faqs.destroy', $faq), 'confirm' => __('Move this question to trash? It will no longer be visible on the site.'), 'label' => __('Move to trash')])
            @endif
        </div>
    </div>
</x-layouts.admin>
