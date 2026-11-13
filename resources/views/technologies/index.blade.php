@php
    // Figma "Chip/Tech" styles per level, shown in the legend with the level name in place of a sample technology.
    $legendStyles = [
        'verified_in_projects' => ['chip' => 'border-line-brand', 'dot' => 'bg-accent'],
        'expertise' => ['chip' => 'border-line', 'dot' => 'bg-brand'],
        'supported' => ['chip' => 'border-line', 'dot' => 'bg-ink-300'],
    ];
    $categoryName = fn ($category): string => $category->translation()?->name ?? $category->key;
@endphp

<x-layouts.public
    :title="__('Technologies & programming languages')"
    :description="__('We choose technology for your goals — not trends. Here is what we work with, and where we have proven delivery experience.')"
    :alternates="\App\Models\Language::active()->keys()->mapWithKeys(fn ($code) => [$code => route('technologies.index', ['locale' => $code])])->all()">
    <x-site.page-hero :eyebrow="__('Technologies')" :title="__('Technologies & programming languages')"
                      :lead="__('We choose technology for your goals — not trends. Here is what we work with, and where we have proven delivery experience.')"
                      :crumbs="[__('Technologies') => null]" />

    <x-site.section inner="flex flex-col gap-10">
        <ul class="flex flex-col gap-4 rounded-lg bg-subtle px-5 py-4 lg:flex-row lg:flex-wrap lg:items-center lg:gap-x-6" aria-label="{{ __('Legend') }}">
            @foreach ($levels as $level)
                <li class="flex flex-wrap items-center gap-x-2.5 gap-y-1">
                    <span class="inline-flex items-center gap-2 rounded-full border bg-surface px-4 py-2 type-label-md text-fg {{ $legendStyles[$level->value]['chip'] }}">
                        <span class="size-2 rounded-full {{ $legendStyles[$level->value]['dot'] }}" aria-hidden="true"></span>{{ $level->label() }}
                    </span>
                    <span class="type-body-sm text-fg-secondary">{{ $level->description() }}</span>
                </li>
            @endforeach
        </ul>

        @if ($categories->isEmpty())
            <x-ui.empty-state :title="__('Our technology list is being updated')" />
        @else
            {{-- Underline tabs (vanilla tabs module): "All" shows every category; without JavaScript every group stays visible. --}}
            <div @if ($categories->count() > 1) data-tabs @endif class="flex flex-col gap-10">
                @if ($categories->count() > 1)
                    <div role="tablist" data-tablist hidden aria-label="{{ __('Technology categories') }}" class="flex max-w-full gap-6 self-start overflow-x-auto border-b border-line">
                        <button type="button" role="tab" data-tab data-tab-all id="tech-tab-all" aria-controls="tech-panel-all" aria-selected="true"
                                class="-mb-px border-b-2 border-transparent pt-2 pb-3 type-label-md whitespace-nowrap text-fg-secondary hover:text-fg aria-selected:border-line-brand aria-selected:text-fg-brand">{{ __('All') }}</button>
                        @foreach ($categories as $category)
                            <button type="button" role="tab" data-tab id="tech-tab-{{ $category->key }}" aria-controls="category-{{ $category->key }}" aria-selected="false"
                                    class="-mb-px border-b-2 border-transparent pt-2 pb-3 type-label-md whitespace-nowrap text-fg-secondary hover:text-fg aria-selected:border-line-brand aria-selected:text-fg-brand">{{ $categoryName($category) }}</button>
                        @endforeach
                    </div>
                @endif

                <div id="tech-panel-all" @if ($categories->count() > 1) role="tabpanel" aria-labelledby="tech-tab-all" @endif class="flex flex-col gap-10 rounded-lg focus-visible:outline-offset-8">
                    @foreach ($categories as $category)
                        <section id="category-{{ $category->key }}" @if ($categories->count() > 1) role="tabpanel" @endif class="flex scroll-mt-24 flex-col gap-4" aria-labelledby="category-{{ $category->key }}-title">
                            <h2 id="category-{{ $category->key }}-title" data-panel-heading class="type-h4 text-fg">{{ $categoryName($category) }}</h2>
                            <ul class="flex flex-wrap gap-2.5">
                                @foreach ($category->technologies as $technology)
                                    <x-site.tech-chip :technology="$technology" />
                                @endforeach
                            </ul>
                        </section>
                    @endforeach
                </div>
            </div>

            <p class="type-body-xs text-fg-tertiary">{{ __('“Verified” is calculated automatically from published portfolio projects. Brand names belong to their owners.') }}</p>
        @endif
    </x-site.section>

    <x-site.cta-band />
</x-layouts.public>
