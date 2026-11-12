<x-layouts.public
    :title="__('Technologies & programming languages')"
    :description="__('We choose technology for your goals — not trends. Here is what we work with, and where we have proven delivery experience.')"
    :alternates="\App\Models\Language::active()->keys()->mapWithKeys(fn ($code) => [$code => route('technologies.index', ['locale' => $code])])->all()">
    <x-site.page-hero :eyebrow="__('Technologies')" :title="__('Technologies & programming languages')"
                      :lead="__('We choose technology for your goals — not trends. Here is what we work with, and where we have proven delivery experience.')"
                      :crumbs="[__('Technologies') => null]" />

    <section class="mx-auto flex max-w-7xl flex-col gap-10 px-5 py-14 lg:px-20 lg:py-20">
        <div class="flex flex-col gap-3 rounded-xl bg-subtle p-4 sm:flex-row sm:flex-wrap sm:items-center sm:gap-6" role="note" aria-label="{{ __('Legend') }}">
            @foreach ($levels as $level)
                @php($dot = ['verified_in_projects' => 'bg-accent', 'expertise' => 'bg-brand', 'supported' => 'bg-fg-tertiary'][$level->value])
                <p class="flex items-center gap-2 text-sm text-fg-secondary">
                    <span class="inline-flex items-center gap-2 rounded-full border bg-surface px-3 py-1 text-xs font-semibold text-fg {{ $level === \App\Enums\TechnologyProficiency::VerifiedInProjects ? 'border-line-brand' : 'border-line' }}">
                        <span class="size-2 rounded-full {{ $dot }}" aria-hidden="true"></span>{{ $level->label() }}
                    </span>
                    {{ $level->description() }}
                </p>
            @endforeach
        </div>

        @if ($categories->isEmpty())
            <x-ui.empty-state :title="__('Our technology list is being updated')" />
        @else
            @if ($categories->count() > 1)
                <nav aria-label="{{ __('Technology categories') }}" class="-mt-4 overflow-x-auto">
                    <ul class="flex gap-5 border-b border-line text-sm font-semibold text-fg-secondary">
                        @foreach ($categories as $category)
                            <li><a href="#category-{{ $category->key }}" class="inline-block whitespace-nowrap border-b-2 border-transparent py-2 hover:border-line-brand hover:text-fg-brand">{{ $category->translation()?->name ?? $category->key }}</a></li>
                        @endforeach
                    </ul>
                </nav>
            @endif

            @foreach ($categories as $category)
                <section id="category-{{ $category->key }}" class="flex scroll-mt-6 flex-col gap-4" aria-labelledby="category-{{ $category->key }}-title">
                    <h2 id="category-{{ $category->key }}-title" class="text-xl font-bold text-fg">{{ $category->translation()?->name ?? $category->key }}</h2>
                    <ul class="flex flex-wrap gap-2">
                        @foreach ($category->technologies as $technology)
                            <x-site.tech-chip :technology="$technology" />
                        @endforeach
                    </ul>
                </section>
            @endforeach

            <p class="text-xs text-fg-tertiary">{{ __('“Verified” is calculated automatically from published portfolio projects. Brand names belong to their owners.') }}</p>
        @endif
    </section>

    <x-site.cta-band />
</x-layouts.public>
