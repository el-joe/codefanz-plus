@php
    $languages = \App\Models\Language::active();
    $featureRows = is_array(old('features')) ? array_values(array_filter(old('features'), 'is_array')) : $features;
    $selectedTechnologies = array_map('intval', (array) old('technology_ids', $product->exists ? $product->technologies->pluck('id')->all() : []));
    $selectedScreenshots = array_values(array_filter((array) old('screenshot_media_ids', $product->exists ? $product->screenshots->pluck('id')->all() : [])));
    $selectedModels = (array) old('pricing_models', $product->pricing_models?->map->value->all() ?? [\App\Enums\PricingModel::CustomQuote->value]);
    $coverId = old('cover_media_id', $product->exists ? $product->coverImage()?->id : null);
    $isPublished = $product->status === \App\Enums\ContentStatus::Published;
@endphp

<x-layouts.admin :title="$product->exists ? __('Edit product') : __('New product')" :crumb="__('Software products')">
    @if ($errors->any())
        <x-ui.alert tone="danger" :title="__('Please fix the highlighted fields')">{{ $errors->first('lock_version') ?: $errors->first('status') ?: __('Some fields need attention.') }}</x-ui.alert>
    @endif

    @if ($pendingChanges->isNotEmpty())
        <x-ui.alert tone="warning" :title="__('Pending AI/MCP proposals for this product')">
            {{ __('Saving here creates a new version, so these proposals will become stale and cannot be applied:') }}
            @foreach ($pendingChanges as $change)
                <a href="{{ route('admin.approvals.show', $change) }}" class="font-mono font-semibold underline">{{ $change->reference }}</a>
            @endforeach
        </x-ui.alert>
    @endif

    <div class="grid gap-6 xl:grid-cols-[1fr_340px]">
        <form id="product-form" method="POST" action="{{ $product->exists ? route('admin.products.update', $product) : route('admin.products.store') }}" class="flex min-w-0 flex-col gap-6">
            @csrf
            @if ($product->exists)
                @method('PUT')
                <input type="hidden" name="lock_version" value="{{ $product->lock_version }}">
            @endif

            @foreach ($languages as $code => $language)
                @php($translation = $product->exists ? $product->exactTranslation($code) : null)
                <x-ui.card :title="$language['native_name']" lang="{{ $code }}" dir="{{ $language['direction'] }}">
                    <div class="flex flex-col gap-4">
                        <x-ui.input :name="'translations['.$code.'][name]'" :id="'name-'.$code" :label="__('Name')" :value="$translation?->name" maxlength="150" />
                        <x-ui.input :name="'translations['.$code.'][slug]'" :id="'slug-'.$code" :label="__('URL slug')" :value="$translation?->slug" :helper="url($code.'/products').'/…'" />
                        <x-ui.input :name="'translations['.$code.'][tagline]'" :id="'tagline-'.$code" :label="__('Tagline')" :value="$translation?->tagline" maxlength="200" />
                        <div class="flex flex-col gap-1.5">
                            <label for="description-{{ $code }}" class="text-sm font-semibold text-fg">{{ __('Description (Markdown)') }}</label>
                            <textarea id="description-{{ $code }}" name="translations[{{ $code }}][description]" rows="8" class="rounded-md border border-line bg-surface px-3 py-2 font-mono text-sm text-fg">{{ old("translations.{$code}.description", $translation?->description) }}</textarea>
                            <p class="text-xs text-fg-tertiary">{{ __('Headings, lists and links are supported. Raw HTML is removed for security.') }}</p>
                            @error("translations.{$code}.description")<p class="text-sm text-fg-danger">{{ $message }}</p>@enderror
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label for="licensing-{{ $code }}" class="text-sm font-semibold text-fg">{{ __('Licensing notes') }}</label>
                            <textarea id="licensing-{{ $code }}" name="translations[{{ $code }}][licensing_notes]" rows="3" maxlength="2000" class="rounded-md border border-line bg-surface px-3 py-2 text-sm text-fg">{{ old("translations.{$code}.licensing_notes", $translation?->licensing_notes) }}</textarea>
                            <p class="text-xs text-fg-tertiary">{{ __('Plain text. Do not include prices: quotes are always preliminary.') }}</p>
                        </div>
                        <x-ui.input :name="'translations['.$code.'][seo_title]'" :id="'seo-title-'.$code" :label="__('SEO title')" :value="$translation?->seo_title" maxlength="70" :helper="__('Up to 70 characters.')" />
                        <x-ui.input :name="'translations['.$code.'][seo_description]'" :id="'seo-description-'.$code" :label="__('Meta description')" :value="$translation?->seo_description" maxlength="170" :helper="__('Up to 170 characters.')" />
                    </div>
                </x-ui.card>
            @endforeach

            @include('admin.services._repeater', [
                'group' => 'features',
                'title' => __('Features'),
                'description' => __('Shown as a feature grid on the product page. Leave every language empty to remove a row.'),
                'rows' => $featureRows,
                'fields' => [
                    'title' => ['label' => __('Feature'), 'multiline' => false, 'max' => 120],
                    'description' => ['label' => __('Short description'), 'multiline' => true, 'max' => 300],
                ],
                'addLabel' => __('Add feature'),
            ])

            @include('admin.portfolio._media-list', [
                'name' => 'screenshot_media_ids',
                'title' => __('Screenshots'),
                'description' => __('Shown on the product page, in this order.'),
                'label' => __('Screenshot'),
                'selected' => $selectedScreenshots,
            ])

            @include('admin.portfolio._technologies', [
                'selected' => $selectedTechnologies,
                'legend' => __('Technologies the product is built with.'),
            ])
        </form>

        <div class="flex flex-col gap-6">
            <x-ui.card :title="__('Publishing')">
                <div class="flex flex-col gap-4">
                    @if ($product->exists)
                        <p class="text-sm text-fg-secondary">{{ __('Status') }}: <x-ui.badge :tone="$isPublished ? 'success' : 'neutral'">{{ $product->status->label() }}</x-ui.badge> · {{ __('Version :version', ['version' => $product->lock_version]) }}</p>
                    @endif
                    <x-ui.button form="product-form">{{ $product->exists ? __('Save changes') : __('Create draft') }}</x-ui.button>
                    @if ($product->exists)
                        <form method="POST" action="{{ route('admin.products.publish', $product) }}">
                            @csrf
                            <x-ui.button variant="secondary" class="w-full">{{ $isPublished ? __('Unpublish') : __('Publish') }}</x-ui.button>
                        </form>
                        @if ($isPublished && ($live = $product->exactTranslation(app()->getLocale()) ?? $product->translation()))
                            <a href="{{ route('products.show', ['locale' => $live->locale, 'slug' => $live->slug]) }}" class="text-sm font-semibold text-fg-brand hover:underline" target="_blank" rel="noopener">{{ __('View live page') }}</a>
                        @endif
                    @endif
                </div>
            </x-ui.card>

            <x-ui.card :title="__('Offer')">
                <div class="flex flex-col gap-4">
                    <x-ui.select name="availability" form="product-form" :label="__('Availability')" :value="$product->availability?->value ?? 'available'" :options="\App\Enums\ProductAvailability::options()" />
                    <fieldset class="flex flex-col gap-2">
                        <legend class="mb-1 text-sm font-semibold text-fg">{{ __('Licensing models') }}</legend>
                        @foreach (\App\Enums\PricingModel::cases() as $model)
                            <label class="flex items-center gap-2 text-sm text-fg">
                                <input type="checkbox" name="pricing_models[]" value="{{ $model->value }}" form="product-form" @checked(in_array($model->value, $selectedModels, true)) class="size-4 accent-brand">
                                {{ $model->label() }}
                            </label>
                        @endforeach
                        <p class="text-xs text-fg-tertiary">{{ __('The first checked model is highlighted. No prices are shown.') }}</p>
                        @error('pricing_models')<p class="text-sm text-fg-danger">{{ $message }}</p>@enderror
                    </fieldset>
                    <label class="flex items-center justify-between gap-3">
                        <span class="text-sm font-semibold text-fg">{{ __('Offer demo requests') }}</span>
                        <input type="checkbox" name="demo_enabled" value="1" form="product-form" @checked(old('demo_enabled', $product->demo_enabled)) class="size-4 accent-brand">
                    </label>
                    <x-ui.input name="docs_url" form="product-form" type="url" :label="__('Documentation link')" :value="$product->docs_url" placeholder="https://" dir="ltr" />
                    <x-ui.select name="icon" form="product-form" :label="__('Icon')" :value="$product->icon" :placeholder="__('No icon')" :options="\App\Enums\ContentIcon::options()" />
                    <x-ui.input name="sort_order" form="product-form" type="number" min="0" max="65535" :label="__('Sort order')" :value="$product->sort_order ?? 0" :helper="__('Lower numbers appear first.')" />
                </div>
            </x-ui.card>

            <x-ui.card :title="__('Cover image')">
                <x-ui.select name="cover_media_id" form="product-form" :label="__('Image from the media library')" :value="$coverId" :placeholder="__('No cover image')"
                             :options="$images->mapWithKeys(fn ($image) => [$image->id => $image->original_name])->all()"
                             :helper="__('Only optimized images are listed. Upload new images in the media library.')" />
            </x-ui.card>

            @if ($product->exists)
                <x-ui.card :title="__('Versions & changelog')">
                    <div class="flex flex-col gap-3">
                        @forelse ($product->versions as $version)
                            <a href="{{ route('admin.products.versions.edit', [$product, $version]) }}" class="flex items-center justify-between gap-3 rounded-md border border-line px-3 py-2 text-sm hover:border-line-brand">
                                <span class="font-mono font-semibold text-fg" dir="ltr">v{{ $version->version }}</span>
                                <span class="text-xs text-fg-tertiary">{{ $version->released_at?->isoFormat('ll') ?? __('Unreleased') }}</span>
                            </a>
                        @empty
                            <p class="text-sm text-fg-secondary">{{ __('No versions yet.') }}</p>
                        @endforelse
                        <x-ui.button variant="secondary" size="sm" :href="route('admin.products.versions.create', $product)">{{ __('Add version') }}</x-ui.button>
                    </div>
                </x-ui.card>

                <x-ui.card>
                    <form method="POST" action="{{ route('admin.products.destroy', $product) }}" data-confirm="{{ __('Move this product to trash? It will no longer be visible on the site.') }}" data-confirm-label="{{ __('Move to trash') }}">
                        @csrf
                        @method('DELETE')
                        <x-ui.button variant="ghost" class="w-full text-fg-danger">{{ __('Move to trash') }}</x-ui.button>
                    </form>
                </x-ui.card>
            @endif
        </div>
    </div>
</x-layouts.admin>
