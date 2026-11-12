@php($isPublished = $industry->status === \App\Enums\ContentStatus::Published)

<x-layouts.admin :title="$industry->exists ? __('Edit industry') : __('New industry')" :crumb="__('Industries')">
    @if ($errors->any())
        <x-ui.alert tone="danger" :title="__('Please fix the highlighted fields')">{{ $errors->first('status') ?: __('Some fields need attention.') }}</x-ui.alert>
    @endif

    <div class="grid gap-6 xl:grid-cols-[1fr_340px]">
        <form id="industry-form" method="POST" action="{{ $industry->exists ? route('admin.industries.update', $industry) : route('admin.industries.store') }}" class="flex min-w-0 flex-col gap-6">
            @csrf
            @if ($industry->exists)
                @method('PUT')
            @endif

            @foreach (\App\Models\Language::active() as $code => $language)
                @php($translation = $industry->exists ? $industry->exactTranslation($code) : null)
                <x-ui.card :title="$language['native_name']" lang="{{ $code }}" dir="{{ $language['direction'] }}">
                    <div class="flex flex-col gap-4">
                        <x-ui.input :name="'translations['.$code.'][name]'" :id="'name-'.$code" :label="__('Name')" :value="$translation?->name" maxlength="150" />
                        <x-ui.input :name="'translations['.$code.'][slug]'" :id="'slug-'.$code" :label="__('URL slug')" :value="$translation?->slug" />
                        <div class="flex flex-col gap-1.5">
                            <label for="summary-{{ $code }}" class="text-sm font-semibold text-fg">{{ __('Summary') }}</label>
                            <textarea id="summary-{{ $code }}" name="translations[{{ $code }}][summary]" rows="3" maxlength="500" class="rounded-md border border-line bg-surface px-3 py-2 text-sm text-fg">{{ old("translations.{$code}.summary", $translation?->summary) }}</textarea>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label for="solutions-{{ $code }}" class="text-sm font-semibold text-fg">{{ __('Typical solutions') }}</label>
                            <textarea id="solutions-{{ $code }}" name="translations[{{ $code }}][typical_solutions]" rows="4" maxlength="1000" aria-describedby="solutions-{{ $code }}-help" class="rounded-md border border-line bg-surface px-3 py-2 text-sm text-fg">{{ old("translations.{$code}.typical_solutions", implode("\n", $translation?->typical_solutions ?? [])) }}</textarea>
                            <p id="solutions-{{ $code }}-help" class="text-xs text-fg-tertiary">{{ __('One per line, up to 12 short labels.') }}</p>
                            @error("translations.{$code}.typical_solutions")<p class="text-sm text-fg-danger">{{ $message }}</p>@enderror
                        </div>
                        <x-ui.input :name="'translations['.$code.'][seo_title]'" :id="'seo-title-'.$code" :label="__('SEO title')" :value="$translation?->seo_title" maxlength="70" />
                        <x-ui.input :name="'translations['.$code.'][seo_description]'" :id="'seo-description-'.$code" :label="__('Meta description')" :value="$translation?->seo_description" maxlength="170" />
                    </div>
                </x-ui.card>
            @endforeach
        </form>

        <div class="flex flex-col gap-6">
            <x-ui.card :title="__('Publishing')">
                <div class="flex flex-col gap-4">
                    @if ($industry->exists)
                        <p class="text-sm text-fg-secondary">{{ __('Status') }}: <x-ui.badge :tone="$isPublished ? 'success' : 'neutral'">{{ $industry->status->label() }}</x-ui.badge></p>
                    @endif
                    <x-ui.select name="icon" form="industry-form" :label="__('Icon')" :value="$industry->icon" :placeholder="__('No icon')" :options="\App\Enums\ContentIcon::options()" />
                    <x-ui.input name="sort_order" form="industry-form" type="number" min="0" max="65535" :label="__('Sort order')" :value="$industry->sort_order ?? 0" />
                    <x-ui.button form="industry-form">{{ $industry->exists ? __('Save changes') : __('Create draft') }}</x-ui.button>
                    @if ($industry->exists)
                        <form method="POST" action="{{ route('admin.industries.publish', $industry) }}">
                            @csrf
                            <x-ui.button variant="secondary" class="w-full">{{ $isPublished ? __('Unpublish') : __('Publish') }}</x-ui.button>
                        </form>
                    @endif
                </div>
            </x-ui.card>

            @if ($industry->exists)
                <x-ui.card>
                    <form method="POST" action="{{ route('admin.industries.destroy', $industry) }}" data-confirm="{{ __('Move this industry to trash? It will no longer be visible on the site.') }}" data-confirm-label="{{ __('Move to trash') }}">
                        @csrf
                        @method('DELETE')
                        <x-ui.button variant="ghost" class="w-full text-fg-danger">{{ __('Move to trash') }}</x-ui.button>
                    </form>
                </x-ui.card>
            @endif
        </div>
    </div>
</x-layouts.admin>
