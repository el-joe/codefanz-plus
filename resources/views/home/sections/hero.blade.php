<section class="bg-[radial-gradient(ellipse_at_top_right,rgb(110_86_255/0.18),transparent_60%)]" aria-labelledby="{{ $headingId }}" data-section="hero">
    <div class="mx-auto grid max-w-7xl items-center gap-12 px-5 py-16 lg:grid-cols-[1.1fr_0.9fr] lg:px-20 lg:py-24">
        <div class="flex flex-col gap-7">
            @if ($copy['eyebrow'])
                <x-ui.badge tone="accent" class="w-fit">{{ $copy['eyebrow'] }}</x-ui.badge>
            @endif
            <h1 id="{{ $headingId }}" class="max-w-4xl text-4xl font-extrabold leading-tight tracking-tight text-fg lg:text-6xl">
                @if ($copy['is_default_heading'])
                    {{ __('Turning Ideas Into') }} <span class="text-fg-brand">{{ __('Powerful Digital Products') }}</span>
                @else
                    {{ $copy['heading'] }}
                @endif
            </h1>
            @if ($copy['subheading'])
                <p class="max-w-2xl text-lg text-fg-secondary">{{ $copy['subheading'] }}</p>
            @endif
            <div class="flex flex-wrap gap-3">
                <x-ui.button :href="$section->ctaUrl() ?? $contactHref" size="lg">
                    {{ $copy['cta_label'] }}
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" class="rtl:-scale-x-100"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
                </x-ui.button>
                <x-ui.button :href="route('portfolio.index')" variant="secondary" size="lg">{{ __('Explore Our Work') }}</x-ui.button>
            </div>
            @if ($copy['points'] !== [])
                <ul class="flex flex-wrap gap-x-6 gap-y-2 text-sm text-fg-secondary">
                    @foreach ($copy['points'] as $point)
                        <li class="flex items-center gap-2">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" class="shrink-0 text-fg-success"><circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/></svg>
                            {{ $point }}
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
        {{-- Decorative product visual (no figures: numbers only ever come from the admin). --}}
        <div class="relative hidden aspect-[4/3.3] overflow-hidden rounded-3xl bg-[linear-gradient(140deg,var(--color-brand-800),var(--color-brand-950)_60%,var(--color-ink-950))] p-8 lg:block" aria-hidden="true">
            <div class="absolute -end-16 -top-16 size-64 rounded-full bg-accent-500/20 blur-3xl"></div>
            <div class="relative grid h-full grid-cols-3 gap-4">
                @foreach (['globe', 'smartphone', 'layers', 'sparkles', 'cloud', 'shield'] as $index => $icon)
                    <div @class(['flex flex-col justify-between rounded-2xl border border-white/10 bg-white/5 p-4 text-white backdrop-blur', 'translate-y-6' => $index % 2 === 1])>
                        <span class="flex size-10 items-center justify-center rounded-xl bg-white/10"><x-ui.icon :name="$icon" :size="22" /></span>
                        <span class="flex flex-col gap-1.5">
                            <span class="h-1.5 w-3/4 rounded-full bg-white/30"></span>
                            <span class="h-1.5 w-1/2 rounded-full bg-white/15"></span>
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>
