@php
    $languages = \App\Models\Language::active();
    $childRows = function (string $group, string $relation, array $fields) use ($service): array {
        if (is_array(old($group))) {
            return array_values(array_filter(old($group), 'is_array'));
        }

        if (! $service->exists) {
            return [];
        }

        return $service->{$relation}->map(fn ($child) => [
            'id' => $child->id,
            'translations' => $child->translations->mapWithKeys(fn ($translation) => [$translation->locale => $translation->only($fields)])->all(),
        ])->all();
    };
    $selectedTechnologies = array_map('intval', (array) old('technology_ids', $service->exists ? $service->technologies->pluck('id')->all() : []));
    $coverId = old('cover_media_id', $service->exists ? $service->coverImage()?->id : null);
    $isPublished = $service->status === \App\Enums\ContentStatus::Published;
@endphp

<x-layouts.admin :title="$service->exists ? __('Edit service') : __('New service')" :crumb="__('Services')">
    @if ($errors->any())
        <x-ui.alert tone="danger" :title="__('Please fix the highlighted fields')">{{ $errors->first('lock_version') ?: $errors->first('status') ?: __('Some fields need attention.') }}</x-ui.alert>
    @endif

    @if ($pendingChanges->isNotEmpty())
        <x-ui.alert tone="warning" :title="__('Pending AI/MCP proposals for this service')">
            {{ __('Saving here creates a new version, so these proposals will become stale and cannot be applied:') }}
            @foreach ($pendingChanges as $change)
                <a href="{{ route('admin.approvals.show', $change) }}" class="font-mono font-semibold underline">{{ $change->reference }}</a>
            @endforeach
        </x-ui.alert>
    @endif

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
        <form id="service-form" method="POST" action="{{ $service->exists ? route('admin.services.update', $service) : route('admin.services.store') }}" class="flex min-w-0 flex-col gap-6">
            @csrf
            @if ($service->exists)
                @method('PUT')
                <input type="hidden" name="lock_version" value="{{ $service->lock_version }}">
            @endif

            @foreach ($languages as $code => $language)
                @php($translation = $service->exists ? $service->exactTranslation($code) : null)
                <x-ui.card :title="$language['native_name']" lang="{{ $code }}" dir="{{ $language['direction'] }}">
                    <div class="flex flex-col gap-4">
                        <x-ui.input :name="'translations['.$code.'][title]'" :id="'title-'.$code" :label="__('Title')" :value="$translation?->title" maxlength="200" />
                        <x-ui.input :name="'translations['.$code.'][slug]'" :id="'slug-'.$code" :label="__('URL slug')" :value="$translation?->slug" :helper="url($code.'/services').'/…'" />
                        <div class="flex flex-col gap-1.5">
                            <label for="summary-{{ $code }}" class="text-sm font-semibold text-fg">{{ __('Summary') }}</label>
                            <textarea id="summary-{{ $code }}" name="translations[{{ $code }}][summary]" rows="2" maxlength="300" class="rounded-md border border-line-strong bg-surface px-3 py-2 text-sm text-fg">{{ old("translations.{$code}.summary", $translation?->summary) }}</textarea>
                            @error("translations.{$code}.summary")<p class="text-sm text-fg-danger">{{ $message }}</p>@enderror
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label for="body-{{ $code }}" class="text-sm font-semibold text-fg">{{ __('Description (Markdown)') }}</label>
                            <textarea id="body-{{ $code }}" name="translations[{{ $code }}][body]" rows="10" class="rounded-md border border-line-strong bg-surface px-3 py-2 font-mono text-sm text-fg">{{ old("translations.{$code}.body", $translation?->body) }}</textarea>
                            <p class="text-xs text-fg-tertiary">{{ __('Headings, lists and links are supported. Raw HTML is removed for security.') }}</p>
                        </div>
                        <x-ui.input :name="'translations['.$code.'][seo_title]'" :id="'seo-title-'.$code" :label="__('SEO title')" :value="$translation?->seo_title" maxlength="70" :helper="__('Up to 70 characters.')" />
                        <x-ui.input :name="'translations['.$code.'][seo_description]'" :id="'seo-description-'.$code" :label="__('Meta description')" :value="$translation?->seo_description" maxlength="170" :helper="__('Up to 170 characters.')" />
                    </div>
                </x-ui.card>
            @endforeach

            @include('admin.services._repeater', [
                'group' => 'features',
                'title' => __('Deliverables & features'),
                'description' => __('Shown as a checklist on the service page. Leave every language empty to remove a row.'),
                'rows' => $childRows('features', 'features', ['title', 'description']),
                'fields' => [
                    'title' => ['label' => __('Feature'), 'multiline' => false, 'max' => 200],
                    'description' => ['label' => __('Short description'), 'multiline' => true, 'max' => 500],
                ],
                'addLabel' => __('Add feature'),
            ])

            @include('admin.services._repeater', [
                'group' => 'faqs',
                'title' => __('Frequently asked questions'),
                'description' => __('Shown as an accordion on the service page. Leave every language empty to remove a row.'),
                'rows' => $childRows('faqs', 'faqs', ['question', 'answer']),
                'fields' => [
                    'question' => ['label' => __('Question'), 'multiline' => false, 'max' => 300],
                    'answer' => ['label' => __('Answer'), 'multiline' => true, 'rows' => 3, 'max' => 5000],
                ],
                'addLabel' => __('Add question'),
            ])

            <x-ui.card :title="__('Tech stack')">
                @if ($technologies->isEmpty())
                    <p class="text-sm text-fg-secondary">{{ __('No technologies yet.') }} <a href="{{ route('admin.technologies.create') }}" class="font-semibold text-fg-brand hover:underline">{{ __('Add a technology') }}</a></p>
                @else
                    <fieldset class="flex flex-col gap-4">
                        <legend class="mb-3 text-sm text-fg-secondary">{{ __('Technologies listed on this service page. Linking a service does not mark a technology as verified.') }}</legend>
                        @foreach ($technologies->groupBy('technology_category_id') as $group)
                            <div class="flex flex-col gap-2">
                                <p class="text-xs font-semibold uppercase text-fg-tertiary">{{ $group->first()->category->translation()?->name ?? $group->first()->category->key }}</p>
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($group as $technology)
                                        <label class="inline-flex items-center gap-2 rounded-full border border-line px-3 py-1.5 text-sm text-fg has-checked:border-line-brand has-checked:bg-brand-subtle">
                                            <input type="checkbox" name="technology_ids[]" value="{{ $technology->id }}" @checked(in_array($technology->id, $selectedTechnologies, true)) class="size-4 accent-brand">
                                            {{ $technology->translation()?->name ?? $technology->key }}
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                        @error('technology_ids')<p class="text-sm text-fg-danger">{{ $message }}</p>@enderror
                    </fieldset>
                @endif
            </x-ui.card>
        </form>

        <div class="flex flex-col gap-6">
            <x-ui.card :title="__('Publishing')">
                <div class="flex flex-col gap-4">
                    @if ($service->exists)
                        <p class="text-sm text-fg-secondary">{{ __('Status') }}: <x-ui.badge :tone="$isPublished ? 'success' : 'neutral'">{{ $service->status->label() }}</x-ui.badge> · {{ __('Version :version', ['version' => $service->lock_version]) }}</p>
                    @endif
                    <x-ui.button form="service-form">{{ $service->exists ? __('Save changes') : __('Create draft') }}</x-ui.button>
                    @if ($service->exists)
                        <form method="POST" action="{{ route('admin.services.publish', $service) }}">
                            @csrf
                            <x-ui.button variant="secondary" class="w-full">{{ $isPublished ? __('Unpublish') : __('Publish') }}</x-ui.button>
                        </form>
                        @if ($isPublished && ($live = $service->exactTranslation(app()->getLocale()) ?? $service->translation()))
                            <a href="{{ route('services.show', ['locale' => $live->locale, 'slug' => $live->slug]) }}" class="text-sm font-semibold text-fg-brand hover:underline" target="_blank" rel="noopener">{{ __('View live page') }}</a>
                        @endif
                    @endif
                </div>
            </x-ui.card>

            <x-ui.card :title="__('Placement')">
                <div class="flex flex-col gap-4">
                    <x-ui.select name="tier" form="service-form" :label="__('Group')" :value="$service->tier?->value ?? 'core'" :options="collect(\App\Enums\ServiceTier::cases())->mapWithKeys(fn ($tier) => [$tier->value => $tier->label()])->all()" />
                    <x-ui.select name="parent_id" form="service-form" :label="__('Parent service')" :value="$service->parent_id" :placeholder="__('None (top-level service)')"
                                 :options="$parents->mapWithKeys(fn ($parent) => [$parent->id => $parent->translation()?->title ?? '#'.$parent->id])->all()"
                                 :helper="__('Sub-services appear on their parent service page.')" />
                    <x-ui.select name="icon" form="service-form" :label="__('Icon')" :value="$service->icon" :placeholder="__('No icon')" :options="\App\Enums\ContentIcon::options()" />
                    <x-ui.input name="sort_order" form="service-form" type="number" min="0" max="65535" :label="__('Sort order')" :value="$service->sort_order ?? 0" :helper="__('Lower numbers appear first.')" />
                    <label class="flex items-center justify-between gap-3">
                        <span class="text-sm font-semibold text-fg">{{ __('Featured on the homepage') }}</span>
                        <input type="checkbox" name="is_featured" value="1" form="service-form" @checked(old('is_featured', $service->is_featured)) class="size-4 accent-brand">
                    </label>
                </div>
            </x-ui.card>

            <x-ui.card :title="__('Cover image')">
                <x-ui.select name="cover_media_id" form="service-form" :label="__('Image from the media library')" :value="$coverId" :placeholder="__('No cover image')"
                             :options="$images->mapWithKeys(fn ($image) => [$image->id => $image->original_name])->all()"
                             :helper="__('Only optimized images are listed. Upload new images in the media library.')" />
            </x-ui.card>

            @if ($service->exists)
                <x-ui.card>
                    <form method="POST" action="{{ route('admin.services.destroy', $service) }}" data-confirm="{{ __('Move this service to trash? It will no longer be visible on the site.') }}" data-confirm-label="{{ __('Move to trash') }}">
                        @csrf
                        @method('DELETE')
                        <x-ui.button variant="ghost" class="w-full text-fg-danger">{{ __('Move to trash') }}</x-ui.button>
                    </form>
                </x-ui.card>
            @endif
        </div>
    </div>
</x-layouts.admin>
