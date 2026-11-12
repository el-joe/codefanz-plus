@php($live = $career->exists ? ($career->exactTranslation(app()->getLocale()) ?? $career->translation()) : null)

<x-layouts.admin :title="$career->exists ? __('Edit position') : __('New position')" :crumb="__('Careers')">
    @if ($errors->any())
        <x-ui.alert tone="danger" :title="__('Please fix the highlighted fields')">{{ $errors->first('status') ?: __('Some fields need attention.') }}</x-ui.alert>
    @endif

    <div class="grid gap-6 xl:grid-cols-[1fr_340px]">
        <form id="career-form" method="POST" action="{{ $career->exists ? route('admin.careers.update', $career) : route('admin.careers.store') }}" class="flex min-w-0 flex-col gap-6">
            @csrf
            @if ($career->exists)
                @method('PUT')
            @endif

            @foreach (\App\Models\Language::active() as $code => $language)
                @php($translation = $career->exists ? $career->exactTranslation($code) : null)
                <x-ui.card :title="$language['native_name']" lang="{{ $code }}" dir="{{ $language['direction'] }}">
                    <div class="flex flex-col gap-4">
                        <x-ui.input :name="'translations['.$code.'][title]'" :id="'title-'.$code" :label="__('Job title')" :value="$translation?->title" maxlength="150" />
                        <x-ui.input :name="'translations['.$code.'][slug]'" :id="'slug-'.$code" :label="__('URL slug')" :value="$translation?->slug" :helper="url($code.'/careers').'/…'" />
                        <div class="grid gap-4 md:grid-cols-2">
                            <x-ui.input :name="'translations['.$code.'][department]'" :id="'department-'.$code" :label="__('Department')" :value="$translation?->department" maxlength="100" />
                            <x-ui.input :name="'translations['.$code.'][location]'" :id="'location-'.$code" :label="__('Location')" :value="$translation?->location" maxlength="150" :helper="__('E.g. Cairo · Hybrid.')" />
                        </div>
                        @include('admin._textarea', ['name' => "translations[{$code}][summary]", 'id' => "summary-{$code}", 'label' => __('Summary'), 'value' => $translation?->summary, 'rows' => 2, 'max' => 300])
                        @include('admin._textarea', ['name' => "translations[{$code}][responsibilities]", 'id' => "responsibilities-{$code}", 'label' => __('Responsibilities'), 'value' => implode("\n", $translation?->responsibilities ?? []), 'rows' => 5, 'help' => __('One per line.')])
                        @include('admin._textarea', ['name' => "translations[{$code}][requirements]", 'id' => "requirements-{$code}", 'label' => __('Requirements'), 'value' => implode("\n", $translation?->requirements ?? []), 'rows' => 5, 'help' => __('One per line.')])
                        @include('admin._textarea', ['name' => "translations[{$code}][body]", 'id' => "body-{$code}", 'label' => __('About the role (Markdown)'), 'value' => $translation?->body, 'rows' => 6, 'mono' => true,
                            'help' => __('Headings, lists and links are supported. Raw HTML is removed for security.')])
                        <x-ui.input :name="'translations['.$code.'][seo_title]'" :id="'seo-title-'.$code" :label="__('SEO title')" :value="$translation?->seo_title" maxlength="70" />
                        <x-ui.input :name="'translations['.$code.'][seo_description]'" :id="'seo-description-'.$code" :label="__('Meta description')" :value="$translation?->seo_description" maxlength="170" />
                    </div>
                </x-ui.card>
            @endforeach
        </form>

        <div class="flex flex-col gap-6">
            @include('admin._publish-card', [
                'model' => $career, 'form' => 'career-form',
                'publishUrl' => $career->exists ? route('admin.careers.publish', $career) : null,
                'liveUrl' => $live && $career->isOpen() ? route('careers.show', ['locale' => $live->locale, 'slug' => $live->slug]) : null,
            ])

            <x-ui.card :title="__('Position')">
                <div class="flex flex-col gap-4">
                    <x-ui.select name="employment_type" form="career-form" :label="__('Employment type')" :value="$career->employment_type?->value ?? 'full_time'" :options="\App\Enums\EmploymentType::options()" />
                    <label class="flex items-center justify-between gap-3">
                        <span class="text-sm font-semibold text-fg">{{ __('Remote friendly') }}</span>
                        <input type="checkbox" name="is_remote" value="1" form="career-form" @checked(old('is_remote', $career->is_remote)) class="size-4 accent-brand">
                    </label>
                    <x-ui.input name="closes_at" form="career-form" type="date" :label="__('Closing date')" :value="$career->closes_at?->toDateString()" :helper="__('The position is hidden after this day. Leave empty to keep it open.')" />
                    <x-ui.input name="sort_order" form="career-form" type="number" min="0" max="65535" :label="__('Sort order')" :value="$career->sort_order ?? 0" :helper="__('Lower numbers appear first.')" />
                </div>
            </x-ui.card>

            @if ($career->exists)
                @include('admin._trash-card', ['action' => route('admin.careers.destroy', $career), 'confirm' => __('Move this position to trash? It will no longer be visible on the site.'), 'label' => __('Move to trash')])
            @endif
        </div>
    </div>
</x-layouts.admin>
