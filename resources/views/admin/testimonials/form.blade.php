@php($avatarId = old('avatar_media_id', $testimonial->exists ? $testimonial->avatarImage()?->id : null))

<x-layouts.admin :title="$testimonial->exists ? __('Edit testimonial') : __('New testimonial')" :crumb="__('Testimonials')">
    @if ($errors->any())
        <x-ui.alert tone="danger" :title="__('Please fix the highlighted fields')">{{ $errors->first('status') ?: __('Some fields need attention.') }}</x-ui.alert>
    @endif

    <div class="grid gap-6 xl:grid-cols-[1fr_340px]">
        <form id="testimonial-form" method="POST" action="{{ $testimonial->exists ? route('admin.testimonials.update', $testimonial) : route('admin.testimonials.store') }}" class="flex min-w-0 flex-col gap-6">
            @csrf
            @if ($testimonial->exists)
                @method('PUT')
            @endif

            <x-ui.card :title="__('Client')">
                <div class="grid gap-4 md:grid-cols-2">
                    <x-ui.input name="author_name" :label="__('Author name')" :value="$testimonial->author_name" maxlength="120" required />
                    <x-ui.input name="company" :label="__('Company')" :value="$testimonial->company" maxlength="150" />
                </div>
            </x-ui.card>

            @foreach (\App\Models\Language::active() as $code => $language)
                @php($translation = $testimonial->exists ? $testimonial->exactTranslation($code) : null)
                <x-ui.card :title="$language['native_name']" lang="{{ $code }}" dir="{{ $language['direction'] }}">
                    <div class="flex flex-col gap-4">
                        @include('admin._textarea', ['name' => "translations[{$code}][quote]", 'id' => "quote-{$code}", 'label' => __('Quote'), 'value' => $translation?->quote, 'rows' => 4, 'max' => 600])
                        <x-ui.input :name="'translations['.$code.'][author_role]'" :id="'role-'.$code" :label="__('Author role')" :value="$translation?->author_role" maxlength="150" :helper="__('E.g. Operations Director.')" />
                    </div>
                </x-ui.card>
            @endforeach
        </form>

        <div class="flex flex-col gap-6">
            <x-ui.card :title="__('Client consent')">
                <div class="flex flex-col gap-3">
                    <label class="flex items-start gap-3">
                        <input type="checkbox" name="consent_confirmed" value="1" form="testimonial-form" @checked(old('consent_confirmed', $testimonial->consent_confirmed)) class="mt-0.5 size-4 accent-brand">
                        <span class="text-sm font-semibold text-fg">{{ __('The client agreed to the publication of this quote, name and company') }}</span>
                    </label>
                    @if ($testimonial->consent_confirmed && $testimonial->consent_confirmed_at)
                        <p class="text-xs text-fg-tertiary">{{ __('Confirmed on :date.', ['date' => $testimonial->consent_confirmed_at->translatedFormat('j M Y')]) }}</p>
                    @endif
                    <p class="text-xs text-fg-tertiary">{{ __('Required before publishing. Unchecking it immediately unpublishes the testimonial.') }}</p>
                </div>
            </x-ui.card>

            @include('admin._publish-card', ['model' => $testimonial, 'form' => 'testimonial-form', 'publishUrl' => $testimonial->exists ? route('admin.testimonials.publish', $testimonial) : null, 'liveUrl' => route('testimonials.index', ['locale' => app()->getLocale()])])

            <x-ui.card :title="__('Details')">
                <div class="flex flex-col gap-4">
                    <x-ui.select name="portfolio_project_id" form="testimonial-form" :label="__('Related project')" :value="$testimonial->portfolio_project_id" :placeholder="__('No project')"
                                 :options="$projects->mapWithKeys(fn ($project) => [$project->id => $project->translation()?->title ?? '#'.$project->id])->all()"
                                 :helper="__('Shown on that project’s case study page.')" />
                    <x-ui.select name="rating" form="testimonial-form" :label="__('Rating')" :value="$testimonial->rating" :placeholder="__('No rating')" :options="[5 => '5', 4 => '4', 3 => '3', 2 => '2', 1 => '1']" />
                    <x-ui.select name="avatar_media_id" form="testimonial-form" :label="__('Photo from the media library')" :value="$avatarId" :placeholder="__('Initials only')"
                                 :options="$images->mapWithKeys(fn ($image) => [$image->id => $image->original_name])->all()" />
                    <x-ui.input name="sort_order" form="testimonial-form" type="number" min="0" max="65535" :label="__('Sort order')" :value="$testimonial->sort_order ?? 0" :helper="__('Lower numbers appear first.')" />
                    <label class="flex items-center justify-between gap-3">
                        <span class="text-sm font-semibold text-fg">{{ __('Feature on the homepage') }}</span>
                        <input type="checkbox" name="is_featured" value="1" form="testimonial-form" @checked(old('is_featured', $testimonial->is_featured)) class="size-4 accent-brand">
                    </label>
                </div>
            </x-ui.card>

            @if ($testimonial->exists)
                @include('admin._trash-card', ['action' => route('admin.testimonials.destroy', $testimonial), 'confirm' => __('Move this testimonial to trash? It will no longer be visible on the site.'), 'label' => __('Move to trash')])
            @endif
        </div>
    </div>
</x-layouts.admin>
