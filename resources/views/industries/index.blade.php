<x-layouts.public
    :title="__('Industries we serve')"
    :description="__('We combine engineering with domain knowledge so your product fits how your industry really works.')"
    :alternates="\App\Models\Language::active()->keys()->mapWithKeys(fn ($code) => [$code => route('industries.index', ['locale' => $code])])->all()">
    <x-site.page-hero :eyebrow="__('Industries')" :title="__('Industries we serve')"
                      :lead="__('We combine engineering with domain knowledge so your product fits how your industry really works.')"
                      :crumbs="[__('Industries') => null]" />

    <x-site.section inner="">
        @if ($industries->isEmpty())
            <x-ui.empty-state :title="__('Our industry list is being updated')" />
        @else
            {{-- Figma "Industry List": two-column cards with icon tile, summary, typical solutions and the related-projects link. --}}
            <ul class="grid gap-6 md:grid-cols-2 md:items-start">
                @foreach ($industries as $industry)
                    @php($translation = $industry->exactTranslation(app()->getLocale()))
                    <li id="{{ $translation->slug }}" class="flex scroll-mt-24 flex-col items-start gap-4 rounded-xl border border-line bg-surface p-6 sm:p-7">
                        <div class="flex items-center gap-3.5">
                            <span class="flex size-13 shrink-0 items-center justify-center rounded-lg bg-accent-subtle text-icon-primary" aria-hidden="true"><x-ui.icon :name="$industry->icon ?? 'briefcase'" :size="26" /></span>
                            <h2 class="type-h4 text-fg">{{ $translation->name }}</h2>
                        </div>
                        @if ($translation->summary)
                            <p class="type-body-md text-fg-secondary">{{ $translation->summary }}</p>
                        @endif
                        @if ($translation->typical_solutions)
                            <ul class="flex flex-wrap gap-2" aria-label="{{ __('Typical solutions') }}">
                                @foreach ($translation->typical_solutions as $solution)
                                    <li class="rounded-full bg-muted px-2.5 py-0.5 type-label-sm font-medium text-fg-secondary">{{ $solution }}</li>
                                @endforeach
                            </ul>
                        @endif
                        @if ($industry->portfolioProjects->isNotEmpty())
                            <div class="flex flex-col gap-1.5 self-stretch border-t border-line-subtle pt-4">
                                <h3 class="type-overline text-fg-tertiary">{{ __('Related projects') }}</h3>
                                <ul class="flex flex-wrap gap-x-4 gap-y-1 type-label-md">
                                    @foreach ($industry->portfolioProjects as $project)
                                        @php($projectTranslation = $project->exactTranslation(app()->getLocale()))
                                        <li><a href="{{ route('portfolio.show', ['slug' => $projectTranslation->slug]) }}" class="text-fg hover:text-fg-brand hover:underline">{{ $projectTranslation->title }}</a></li>
                                    @endforeach
                                </ul>
                            </div>
                            <x-ui.button :href="route('portfolio.index', ['industry' => $translation->slug])" variant="ghost" size="sm" arrow>
                                {{ __('See related projects') }}<span class="sr-only"> — {{ $translation->name }}</span>
                            </x-ui.button>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </x-site.section>

    <x-site.cta-band :title="__('Don’t see your industry?')" :lead="__('We learn fast. Tell us about your business and its workflows.')" />
</x-layouts.public>
