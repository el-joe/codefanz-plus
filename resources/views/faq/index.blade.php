<x-layouts.public
    :title="__('Frequently asked questions')"
    :description="__('Answers about pricing, timelines, payments, ownership and support.')"
    :alternates="\App\Models\Language::active()->keys()->mapWithKeys(fn ($code) => [$code => route('faq.index', ['locale' => $code])])->all()">
    <x-site.page-hero :eyebrow="__('Help center')" :title="__('Frequently asked questions')"
                      :lead="__('Answers about pricing, timelines, payments, ownership and support.')"
                      :crumbs="[__('FAQ') => null]" />

    <x-site.section tone="surface" inner="mx-auto flex max-w-[45rem] flex-col gap-10" data-faq aria-label="{{ __('Questions') }}">
        @if ($groups->isEmpty())
            <x-ui.empty-state :title="__('Our FAQ is being updated')" :description="__('Please contact us with any question.')" />
        @else
            <div data-faq-search hidden class="flex flex-col gap-2">
                <label for="faq-search" class="sr-only">{{ __('Search questions') }}</label>
                <div class="relative">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="pointer-events-none absolute start-3 top-1/2 -translate-y-1/2 text-icon-secondary"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                    <input id="faq-search" type="search" autocomplete="off" placeholder="{{ __('Search questions…') }}"
                           class="h-12 w-full rounded-md border border-line bg-surface pe-3 ps-10 type-body-md text-fg shadow-xs placeholder:text-fg-tertiary focus:border-line-focus focus:outline-none focus:ring-4 focus:ring-brand/20">
                </div>
                <p class="sr-only" aria-live="polite" data-faq-status data-template="{{ __(':count matching questions') }}"></p>
            </div>

            @foreach ($groups as $group)
                @php($heading = $group['category']?->translation()?->name ?? ($groups->count() > 1 ? __('Other questions') : null))
                <div data-faq-group class="flex flex-col gap-3" @if ($heading) role="group" aria-labelledby="faq-group-{{ $loop->index }}" @endif>
                    @if ($heading)
                        <h2 id="faq-group-{{ $loop->index }}" class="mb-1 type-h5 text-fg sm:type-h4">{{ $heading }}</h2>
                    @endif
                    @foreach ($group['faqs'] as $faq)
                        <x-site.faq-item :translation="$faq->exactTranslation(app()->getLocale())" :open="$loop->parent->first && $loop->first" />
                    @endforeach
                </div>
            @endforeach

            <p data-faq-empty hidden class="rounded-xl border border-dashed border-line px-5 py-8 text-center type-body-sm text-fg-secondary">{{ __('No questions match your search.') }}</p>
        @endif
    </x-site.section>

    @include('faq._schema', ['faqs' => $faqs])

    <x-site.cta-band :title="__('Still have questions?')" :lead="__('Talk to our team or describe your idea to the AI Project Assistant.')" :action="__('Contact us')" :href="route('contact')" />
</x-layouts.public>
