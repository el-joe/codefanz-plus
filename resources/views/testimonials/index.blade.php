<x-layouts.public
    :title="__('What our clients say')"
    :description="__('Published with each client’s permission. Read the full case studies in our portfolio.')"
    :alternates="\App\Models\Language::active()->keys()->mapWithKeys(fn ($code) => [$code => route('testimonials.index', ['locale' => $code])])->all()">
    <x-site.page-hero :eyebrow="__('Client stories')" :title="__('What our clients say')"
                      :lead="__('Published with each client’s permission. Read the full case studies in our portfolio.')"
                      :crumbs="[__('Testimonials') => null]" />

    <x-site.section tone="surface" inner="" aria-label="{{ __('Testimonials') }}">
        @if ($testimonials->isEmpty())
            <x-ui.empty-state :title="__('Client stories are coming soon')" :description="__('Meanwhile, explore the projects we have delivered.')">
                <x-ui.button :href="route('portfolio.index')" variant="secondary" class="mt-2">{{ __('View our portfolio') }}</x-ui.button>
            </x-ui.empty-state>
        @else
            <ul class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($testimonials as $testimonial)
                    <x-site.testimonial-card :testimonial="$testimonial">
                        @if ($testimonial->project && ($projectTranslation = $testimonial->project->exactTranslation(app()->getLocale())))
                            <a href="{{ route('portfolio.show', ['slug' => $projectTranslation->slug]) }}" class="mt-5 inline-flex items-center gap-1.5 border-t border-line-subtle pt-4 type-label-md text-fg-brand hover:underline">
                                {{ __('Read the case study: :project', ['project' => $projectTranslation->title]) }}
                                <x-ui.arrow :size="16" />
                            </a>
                        @endif
                    </x-site.testimonial-card>
                @endforeach
            </ul>
        @endif
    </x-site.section>

    <x-site.cta-band :title="__('Ready to be our next success story?')" :lead="__('Get a free preliminary estimate within 48 hours.')" />
</x-layouts.public>
