<x-layouts.public
    :title="__('Industries we serve')"
    :description="__('We combine engineering with domain knowledge so your product fits how your industry really works.')"
    :alternates="\App\Models\Language::active()->keys()->mapWithKeys(fn ($code) => [$code => route('industries.index', ['locale' => $code])])->all()">
    <x-site.page-hero :eyebrow="__('Industries')" :title="__('Industries we serve')"
                      :lead="__('We combine engineering with domain knowledge so your product fits how your industry really works.')"
                      :crumbs="[__('Industries') => null]" />

    <section class="mx-auto max-w-7xl px-5 py-14 lg:px-20 lg:py-20">
        @if ($industries->isEmpty())
            <x-ui.empty-state :title="__('Our industry list is being updated')" />
        @else
            <ul class="grid gap-5 md:grid-cols-2">
                @foreach ($industries as $industry)
                    @php($translation = $industry->exactTranslation(app()->getLocale()))
                    <li id="{{ $translation->slug }}" class="flex scroll-mt-6 flex-col gap-3 rounded-xl border border-line bg-surface p-6">
                        <div class="flex items-center gap-3">
                            <span class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-accent-subtle text-fg-accent"><x-ui.icon :name="$industry->icon ?? 'briefcase'" /></span>
                            <h2 class="text-lg font-semibold text-fg">{{ $translation->name }}</h2>
                        </div>
                        @if ($translation->summary)
                            <p class="text-sm text-fg-secondary">{{ $translation->summary }}</p>
                        @endif
                        @if ($translation->typical_solutions)
                            <ul class="flex flex-wrap gap-1.5" aria-label="{{ __('Typical solutions') }}">
                                @foreach ($translation->typical_solutions as $solution)
                                    <li class="rounded-full bg-muted px-2.5 py-0.5 text-xs font-medium text-fg-secondary">{{ $solution }}</li>
                                @endforeach
                            </ul>
                        @endif
                        @if ($industry->portfolioProjects->isNotEmpty())
                            <div class="mt-auto flex flex-col gap-1.5 border-t border-line-subtle pt-3">
                                <p class="text-xs font-semibold uppercase text-fg-tertiary">{{ __('Related projects') }}</p>
                                <ul class="flex flex-col gap-1 text-sm">
                                    @foreach ($industry->portfolioProjects as $project)
                                        @php($projectTranslation = $project->exactTranslation(app()->getLocale()))
                                        <li><a href="{{ route('portfolio.show', ['slug' => $projectTranslation->slug]) }}" class="font-semibold text-fg-brand hover:underline">{{ $projectTranslation->title }}</a></li>
                                    @endforeach
                                </ul>
                                <a href="{{ route('portfolio.index', ['industry' => $translation->slug]) }}" class="text-xs font-semibold text-fg-secondary hover:text-fg-brand">{{ __('All :industry projects', ['industry' => $translation->name]) }}</a>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <x-site.cta-band :title="__('Don’t see your industry?')" :lead="__('We learn fast. Tell us about your business and its workflows.')" />
</x-layouts.public>
