@php
    // Figma "Technologies": pill tabs per category (vanilla tabs module); without JavaScript every group stays visible.
    $groups = $data->groupBy(fn ($technology) => $technology->category?->key ?? 'other')
        ->sortBy(fn ($technologies) => $technologies->first()->category?->sort_order ?? PHP_INT_MAX);
    $groupName = fn ($technologies, string $key): string => $technologies->first()->category?->translation()?->name ?? ($key === 'other' ? __('Other') : $key);
    $levels = $data->map(fn ($technology) => $technology->proficiency())->unique(fn ($level) => $level->value)->sortBy(fn ($level) => $level === \App\Enums\TechnologyProficiency::VerifiedInProjects ? 0 : 1);
    $legend = [
        'verified_in_projects' => ['dot' => 'bg-accent', 'label' => __('Used in delivered projects')],
        'expertise' => ['dot' => 'bg-brand', 'label' => __('Production expertise')],
        'supported' => ['dot' => 'bg-ink-300', 'label' => __('Supported')],
    ];
@endphp
<x-site.section :tone="$tone" aria-labelledby="{{ $headingId }}" data-section="technologies">
    <x-site.section-heading :id="$headingId" :eyebrow="$copy['eyebrow']" :heading="$copy['heading']" :subheading="$copy['subheading']" />

    @if ($groups->count() > 1)
        <div data-tabs class="flex flex-col items-center gap-12">
            <div role="tablist" data-tablist hidden aria-label="{{ __('Technology categories') }}" class="flex max-w-full gap-1 overflow-x-auto rounded-full border border-v2-hairline bg-v2-tint p-1">
                @foreach ($groups as $key => $technologies)
                    <button type="button" role="tab" data-tab id="tech-tab-{{ $key }}" aria-controls="tech-panel-{{ $key }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}"
                            class="rounded-full px-3.5 py-1.5 type-label-md whitespace-nowrap text-fg-secondary hover:text-fg aria-selected:bg-surface aria-selected:text-fg aria-selected:shadow-v2-card">{{ $groupName($technologies, $key) }}</button>
                @endforeach
            </div>
            <div class="w-full">
                @foreach ($groups as $key => $technologies)
                    <div id="tech-panel-{{ $key }}" role="tabpanel" aria-labelledby="tech-tab-{{ $key }}" class="flex flex-col items-center gap-4 rounded-lg focus-visible:outline-offset-8 [&+&]:mt-10">
                        <h3 data-panel-heading class="type-label-md text-fg-secondary">{{ $groupName($technologies, $key) }}</h3>
                        <ul class="flex flex-wrap justify-center gap-3">
                            @foreach ($technologies as $technology)
                                <x-site.tech-chip :technology="$technology" :href="route('portfolio.index', ['technology' => $technology->key])" />
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </div>
    @else
        <ul class="flex flex-wrap justify-center gap-3">
            @foreach ($data as $technology)
                <x-site.tech-chip :technology="$technology" :href="route('portfolio.index', ['technology' => $technology->key])" />
            @endforeach
        </ul>
    @endif

    <div class="flex flex-col items-center gap-6">
        <ul class="flex flex-wrap justify-center gap-x-5 gap-y-2 type-body-xs text-fg-tertiary" aria-label="{{ __('Legend') }}">
            @foreach ($levels as $level)
                <li class="flex items-center gap-2"><span class="size-2 rounded-full {{ $legend[$level->value]['dot'] }}" aria-hidden="true"></span>{{ $legend[$level->value]['label'] }}</li>
            @endforeach
        </ul>
        @if ($copy['cta_label'])
            <x-ui.button :href="route('technologies.index')" variant="ghost" arrow>{{ $copy['cta_label'] }}</x-ui.button>
        @endif
    </div>
</x-site.section>
