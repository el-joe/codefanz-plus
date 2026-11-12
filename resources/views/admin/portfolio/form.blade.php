@php
    $languages = \App\Models\Language::active();
    $keyFactRows = is_array(old('key_facts')) ? array_values(array_filter(old('key_facts'), 'is_array')) : $keyFacts;
    $selectedTechnologies = array_map('intval', (array) old('technology_ids', $project->exists ? $project->technologies->pluck('id')->all() : []));
    $selectedServices = array_map('intval', (array) old('service_ids', $project->exists ? $project->services->pluck('id')->all() : []));
    $selectedGallery = array_values(array_filter((array) old('gallery_media_ids', $project->exists ? $project->gallery->pluck('id')->all() : [])));
    $coverId = old('cover_media_id', $project->exists ? $project->coverImage()?->id : null);
    $isPublished = $project->status === \App\Enums\ContentStatus::Published;
    $markdownHelp = __('Headings, lists and links are supported. Raw HTML is removed for security.');
@endphp

<x-layouts.admin :title="$project->exists ? __('Edit project') : __('New project')" :crumb="__('Portfolio')">
    @if ($errors->any())
        <x-ui.alert tone="danger" :title="__('Please fix the highlighted fields')">{{ $errors->first('lock_version') ?: $errors->first('status') ?: __('Some fields need attention.') }}</x-ui.alert>
    @endif

    @if ($pendingChanges->isNotEmpty())
        <x-ui.alert tone="warning" :title="__('Pending AI/MCP proposals for this project')">
            {{ __('Saving here creates a new version, so these proposals will become stale and cannot be applied:') }}
            @foreach ($pendingChanges as $change)
                <a href="{{ route('admin.approvals.show', $change) }}" class="font-mono font-semibold underline">{{ $change->reference }}</a>
            @endforeach
        </x-ui.alert>
    @endif

    <div class="grid gap-6 xl:grid-cols-[1fr_340px]">
        <form id="project-form" method="POST" action="{{ $project->exists ? route('admin.portfolio.update', $project) : route('admin.portfolio.store') }}" class="flex min-w-0 flex-col gap-6">
            @csrf
            @if ($project->exists)
                @method('PUT')
                <input type="hidden" name="lock_version" value="{{ $project->lock_version }}">
            @endif

            @foreach ($languages as $code => $language)
                @php($translation = $project->exists ? $project->exactTranslation($code) : null)
                <x-ui.card :title="$language['native_name']" lang="{{ $code }}" dir="{{ $language['direction'] }}">
                    <div class="flex flex-col gap-4">
                        <x-ui.input :name="'translations['.$code.'][title]'" :id="'title-'.$code" :label="__('Title')" :value="$translation?->title" maxlength="200" />
                        <x-ui.input :name="'translations['.$code.'][slug]'" :id="'slug-'.$code" :label="__('URL slug')" :value="$translation?->slug" :helper="url($code.'/portfolio').'/…'" />
                        <div class="flex flex-col gap-1.5">
                            <label for="summary-{{ $code }}" class="text-sm font-semibold text-fg">{{ __('Summary') }}</label>
                            <textarea id="summary-{{ $code }}" name="translations[{{ $code }}][summary]" rows="2" maxlength="300" class="rounded-md border border-line bg-surface px-3 py-2 text-sm text-fg">{{ old("translations.{$code}.summary", $translation?->summary) }}</textarea>
                            @error("translations.{$code}.summary")<p class="text-sm text-fg-danger">{{ $message }}</p>@enderror
                        </div>
                        @foreach (['challenge' => [__('The challenge (Markdown)'), 5], 'solution' => [__('Our solution (Markdown)'), 6], 'results' => [__('Results (Markdown)'), 6]] as $field => [$fieldLabel, $rows])
                            <div class="flex flex-col gap-1.5">
                                <label for="{{ $field }}-{{ $code }}" class="text-sm font-semibold text-fg">{{ $fieldLabel }}</label>
                                <textarea id="{{ $field }}-{{ $code }}" name="translations[{{ $code }}][{{ $field }}]" rows="{{ $rows }}" class="rounded-md border border-line bg-surface px-3 py-2 font-mono text-sm text-fg">{{ old("translations.{$code}.{$field}", $translation?->{$field}) }}</textarea>
                                @error("translations.{$code}.{$field}")<p class="text-sm text-fg-danger">{{ $message }}</p>@enderror
                            </div>
                        @endforeach
                        <p class="text-xs text-fg-tertiary">{{ $markdownHelp }}</p>
                        <x-ui.input :name="'translations['.$code.'][seo_title]'" :id="'seo-title-'.$code" :label="__('SEO title')" :value="$translation?->seo_title" maxlength="70" :helper="__('Up to 70 characters.')" />
                        <x-ui.input :name="'translations['.$code.'][seo_description]'" :id="'seo-description-'.$code" :label="__('Meta description')" :value="$translation?->seo_description" maxlength="170" :helper="__('Up to 170 characters.')" />
                    </div>
                </x-ui.card>
            @endforeach

            @include('admin.services._repeater', [
                'group' => 'key_facts',
                'title' => __('Key facts'),
                'description' => __('Short label and value pairs shown in the project facts sidebar, e.g. “Timeline” – “14 weeks”. Leave every language empty to remove a row.'),
                'rows' => $keyFactRows,
                'fields' => [
                    'label' => ['label' => __('Label'), 'multiline' => false, 'max' => 60],
                    'value' => ['label' => __('Value'), 'multiline' => false, 'max' => 120],
                ],
                'addLabel' => __('Add fact'),
            ])

            @include('admin.portfolio._media-list', [
                'name' => 'gallery_media_ids',
                'title' => __('Gallery'),
                'description' => __('Screenshots shown under the project header, in this order.'),
                'label' => __('Gallery image'),
                'selected' => $selectedGallery,
            ])

            @include('admin.portfolio._technologies', [
                'selected' => $selectedTechnologies,
                'legend' => __('Technologies used in this project. When the project is published and public, they show as “Verified in projects”.'),
            ])

            <x-ui.card :title="__('Services delivered')">
                @if ($services->isEmpty())
                    <p class="text-sm text-fg-secondary">{{ __('No published services yet.') }}</p>
                @else
                    <fieldset class="flex flex-wrap gap-2">
                        <legend class="mb-3 text-sm text-fg-secondary">{{ __('The project appears as related work on these service pages.') }}</legend>
                        @foreach ($services as $service)
                            <label class="inline-flex items-center gap-2 rounded-full border border-line px-3 py-1.5 text-sm text-fg has-checked:border-line-brand has-checked:bg-brand-subtle">
                                <input type="checkbox" name="service_ids[]" value="{{ $service->id }}" @checked(in_array($service->id, $selectedServices, true)) class="size-4 accent-brand">
                                {{ $service->translation()?->title ?? '#'.$service->id }}
                            </label>
                        @endforeach
                    </fieldset>
                    @error('service_ids.*')<p class="mt-2 text-sm text-fg-danger">{{ $message }}</p>@enderror
                @endif
            </x-ui.card>
        </form>

        <div class="flex flex-col gap-6">
            <x-ui.card :title="__('Publishing')">
                <div class="flex flex-col gap-4">
                    @if ($project->exists)
                        <p class="text-sm text-fg-secondary">{{ __('Status') }}: <x-ui.badge :tone="$isPublished ? 'success' : 'neutral'">{{ $project->status->label() }}</x-ui.badge> · {{ __('Version :version', ['version' => $project->lock_version]) }}</p>
                    @endif
                    <x-ui.select name="visibility" form="project-form" :label="__('Visibility')" :value="$project->visibility?->value ?? 'public'" :options="\App\Enums\ProjectVisibility::options()"
                                 :helper="__('Unlisted projects are not listed, not indexed and do not verify technologies.')" />
                    <x-ui.button form="project-form">{{ $project->exists ? __('Save changes') : __('Create draft') }}</x-ui.button>
                    @if ($project->exists)
                        <form method="POST" action="{{ route('admin.portfolio.publish', $project) }}">
                            @csrf
                            <x-ui.button variant="secondary" class="w-full">{{ $isPublished ? __('Unpublish') : __('Publish') }}</x-ui.button>
                        </form>
                        @if ($isPublished && ($live = $project->exactTranslation(app()->getLocale()) ?? $project->translation()))
                            <a href="{{ route('portfolio.show', ['locale' => $live->locale, 'slug' => $live->slug]) }}" class="text-sm font-semibold text-fg-brand hover:underline" target="_blank" rel="noopener">{{ __('View live page') }}</a>
                        @endif
                    @endif
                </div>
            </x-ui.card>

            <x-ui.card :title="__('Classification')">
                <div class="flex flex-col gap-4">
                    <x-ui.select name="type" form="project-form" :label="__('Project type')" :value="$project->type?->value ?? 'web'" :options="\App\Enums\ProjectType::options()" />
                    <x-ui.select name="portfolio_category_id" form="project-form" :label="__('Category')" :value="$project->portfolio_category_id" :placeholder="__('No category')"
                                 :options="$categories->mapWithKeys(fn ($category) => [$category->id => $category->translation()?->name ?? '#'.$category->id])->all()" />
                    <x-ui.select name="industry_id" form="project-form" :label="__('Industry')" :value="$project->industry_id" :placeholder="__('No industry')"
                                 :options="$industries->mapWithKeys(fn ($industry) => [$industry->id => $industry->translation()?->name ?? '#'.$industry->id])->all()" />
                    <x-ui.input name="project_date" form="project-form" type="date" :label="__('Project date')" :value="$project->project_date?->toDateString()" />
                    <x-ui.input name="sort_order" form="project-form" type="number" min="0" max="65535" :label="__('Sort order')" :value="$project->sort_order ?? 0" :helper="__('Lower numbers appear first.')" />
                    <label class="flex items-center justify-between gap-3">
                        <span class="text-sm font-semibold text-fg">{{ __('Featured project') }}</span>
                        <input type="checkbox" name="is_featured" value="1" form="project-form" @checked(old('is_featured', $project->is_featured)) class="size-4 accent-brand">
                    </label>
                </div>
            </x-ui.card>

            <x-ui.card :title="__('Client')">
                <div class="flex flex-col gap-4">
                    <x-ui.input name="client_name" form="project-form" :label="__('Client name')" :value="$project->client_name" maxlength="150" />
                    <label class="flex items-center justify-between gap-3">
                        <span class="text-sm font-semibold text-fg">{{ __('Client agreed to be named publicly') }}</span>
                        <input type="checkbox" name="is_client_public" value="1" form="project-form" @checked(old('is_client_public', $project->is_client_public)) class="size-4 accent-brand">
                    </label>
                    <p class="text-xs text-fg-tertiary">{{ __('When unchecked, visitors see “Confidential client” and the name never leaves the admin.') }}</p>
                </div>
            </x-ui.card>

            <x-ui.card :title="__('Links')">
                <div class="flex flex-col gap-4">
                    <x-ui.input name="live_url" form="project-form" type="url" :label="__('Live website')" :value="$project->live_url" placeholder="https://" dir="ltr" />
                    <x-ui.input name="android_url" form="project-form" type="url" :label="__('Google Play link')" :value="$project->android_url" placeholder="https://" dir="ltr" />
                    <x-ui.input name="ios_url" form="project-form" type="url" :label="__('App Store link')" :value="$project->ios_url" placeholder="https://" dir="ltr" />
                    <x-ui.input name="github_url" form="project-form" type="url" :label="__('Source code link')" :value="$project->github_url" placeholder="https://" dir="ltr" />
                    <x-ui.input name="video_url" form="project-form" type="url" :label="__('Video (YouTube or Vimeo)')" :value="$project->video_url" placeholder="https://" dir="ltr" />
                </div>
            </x-ui.card>

            <x-ui.card :title="__('Cover image')">
                <x-ui.select name="cover_media_id" form="project-form" :label="__('Image from the media library')" :value="$coverId" :placeholder="__('No cover image')"
                             :options="$images->mapWithKeys(fn ($image) => [$image->id => $image->original_name])->all()"
                             :helper="__('Only optimized images are listed. Upload new images in the media library.')" />
            </x-ui.card>

            @if ($project->exists)
                <x-ui.card>
                    <form method="POST" action="{{ route('admin.portfolio.destroy', $project) }}" data-confirm="{{ __('Move this project to trash? It will no longer be visible on the site.') }}" data-confirm-label="{{ __('Move to trash') }}">
                        @csrf
                        @method('DELETE')
                        <x-ui.button variant="ghost" class="w-full text-fg-danger">{{ __('Move to trash') }}</x-ui.button>
                    </form>
                </x-ui.card>
            @endif
        </div>
    </div>
</x-layouts.admin>
