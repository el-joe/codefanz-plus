@php
    $type = $section->type;
    $itemRows = is_array(old('items')) ? array_values(array_filter(old('items'), 'is_array')) : $items;
    $limit = $type->limit();
@endphp

<x-layouts.admin :title="__('Section: :section', ['section' => $type->label()])" :crumb="\App\Models\PageSection::pageLabel($section->page)">
    @if ($errors->any())
        <x-ui.alert tone="danger" :title="__('Please fix the highlighted fields')">{{ __('Some fields need attention.') }}</x-ui.alert>
    @endif
    @unless ($type->isAvailable())
        <x-ui.alert tone="warning" :title="__('Not shown yet')">{{ $type->source() }}</x-ui.alert>
    @endunless

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
        <form id="section-form" method="POST" action="{{ route('admin.home-sections.update', $section) }}" class="flex min-w-0 flex-col gap-6">
            @csrf
            @method('PUT')

            <p class="text-sm text-fg-secondary">{{ __('Leave a field empty to use the default wording shown as a placeholder. Content: :source.', ['source' => $type->source()]) }}</p>

            @foreach (\App\Models\Language::active() as $code => $language)
                @php
                    $translation = $section->exactTranslation($code);
                    $placeholders = array_map(fn ($text) => (string) $text, $type->defaults($code));
                @endphp
                <x-ui.card :title="$language['native_name']" lang="{{ $code }}" dir="{{ $language['direction'] }}">
                    <div class="flex flex-col gap-4">
                        <x-ui.input :name="'translations['.$code.'][eyebrow]'" :id="'eyebrow-'.$code" :label="__('Eyebrow')" :value="$translation?->eyebrow" maxlength="80" :placeholder="$placeholders['eyebrow']" />
                        <x-ui.input :name="'translations['.$code.'][heading]'" :id="'heading-'.$code" :label="__('Heading')" :value="$translation?->heading" maxlength="200" :placeholder="$placeholders['heading']" />
                        <x-ui.input :name="'translations['.$code.'][subheading]'" :id="'subheading-'.$code" :label="__('Subheading')" :value="$translation?->subheading" maxlength="500" :placeholder="$placeholders['subheading']" />
                        <x-ui.input :name="'translations['.$code.'][cta_label]'" :id="'cta-'.$code" :label="__('Button / link label')" :value="$translation?->cta_label" maxlength="60" :placeholder="$placeholders['cta_label']" />
                        @if ($type->hasPoints())
                            @include('admin._textarea', [
                                'name' => "translations[{$code}][points]", 'id' => "points-{$code}", 'rows' => 4, 'max' => 700,
                                'label' => $type === \App\Enums\PageSectionType::Hero ? __('Highlights') : __('Delivery promise'),
                                'value' => implode("\n", $translation?->points ?? []),
                                'help' => __('One per line, up to 6 short lines.'),
                            ])
                        @endif
                    </div>
                </x-ui.card>
            @endforeach

            @if ($type->hasItems())
                @include('admin.services._repeater', [
                    'group' => 'items',
                    'title' => $type->itemsLabel(),
                    'description' => __('Shown in this order. Leave every language empty to remove a row.'),
                    'rows' => $itemRows,
                    'fields' => [
                        'title' => ['label' => __('Title'), 'multiline' => false, 'max' => 80],
                        'text' => ['label' => __('Text'), 'multiline' => true, 'max' => 300],
                    ],
                    'addLabel' => __('Add row'),
                ])
            @endif
        </form>

        <div class="flex flex-col gap-6">
            <x-ui.card :title="__('Display')">
                <div class="flex flex-col gap-4">
                    <label class="flex items-center justify-between gap-3">
                        <span class="text-sm font-semibold text-fg">{{ __('Show on the page') }}</span>
                        <input type="checkbox" name="is_visible" value="1" form="section-form" @checked(old('is_visible', $section->is_visible)) class="size-4 accent-brand">
                    </label>
                    @if ($limit)
                        <x-ui.input name="limit" form="section-form" type="number" min="1" :max="$limit['max']" :label="__('Number of items')" :value="$section->settings['limit'] ?? null"
                                    :placeholder="(string) $limit['default']" :helper="__('Default :default, up to :max.', ['default' => $limit['default'], 'max' => $limit['max']])" />
                    @endif
                    @if ($type->hasCustomCtaUrl())
                        <x-ui.input name="cta_url" form="section-form" :label="__('Button link')" :value="$section->ctaUrl()" dir="ltr" placeholder="https://"
                                    :helper="__('Leave empty to open the contact page.')" />
                    @endif
                    <x-ui.button form="section-form">{{ __('Save changes') }}</x-ui.button>
                    <a href="{{ $section->pageUrl() }}" target="_blank" rel="noopener" class="text-sm font-semibold text-fg-brand hover:underline">{{ __('View page') }}</a>
                    <a href="{{ route('admin.home-sections.index', ['page' => $section->page]) }}" class="text-sm font-semibold text-fg-secondary hover:underline">{{ __('Back to sections') }}</a>
                </div>
            </x-ui.card>
        </div>
    </div>
</x-layouts.admin>
