{{--
    Figma "Hero" (v2): grid backdrop with two glows (indigo top-start, teal bottom-end; mirrored in RTL), v2 display type
    with the gradient second line, and the generated glass artwork (LCP image, preloaded on the home page) with the
    AI assistant chat bubble as a glass chip that opens the assistant. The v1 dashboard mock (invented figures) is gone.
--}}
<section class="relative isolate overflow-hidden bg-v2-canvas" aria-labelledby="{{ $headingId }}" data-section="hero">
    <div class="pointer-events-none absolute inset-0 -z-10" aria-hidden="true">
        <div class="absolute inset-0 bg-grid-v2"></div>
        <div class="absolute -start-[16.25rem] -top-[16.25rem] size-[52.5rem] rounded-full bg-[radial-gradient(closest-side,rgb(110_86_255/0.22),transparent)]"></div>
        <div class="absolute start-[54%] top-[9.7rem] size-[39.375rem] rounded-full bg-[radial-gradient(closest-side,rgb(26_213_200/0.18),transparent)] max-xl:start-[30%] max-xl:top-auto max-xl:bottom-0"></div>
    </div>
    <div class="container-page grid grid-cols-1 items-center gap-10 pt-10 pb-14 lg:pt-24 lg:pb-28 xl:grid-cols-[minmax(0,41rem)_35rem] xl:justify-between xl:gap-16">
        <div class="flex flex-col items-start gap-5 lg:gap-7">
            @if ($copy['eyebrow'])
                <x-ui.badge tone="accent" class="w-fit">{{ $copy['eyebrow'] }}</x-ui.badge>
            @endif
            <h1 id="{{ $headingId }}" class="type-display-m text-v2-display lg:type-display-xl">
                @if ($copy['is_default_heading'])
                    {{ __('Turning Ideas Into') }} <span class="text-gradient-v2 box-decoration-clone">{{ __('Powerful Digital Products') }}</span>
                @else
                    {{ $copy['heading'] }}
                @endif
            </h1>
            @if ($copy['subheading'])
                <p class="max-w-[41rem] type-body-lg text-fg-secondary lg:type-lead">{{ $copy['subheading'] }}</p>
            @endif
            <div class="flex w-full flex-col gap-3 sm:w-auto sm:flex-row">
                <x-ui.button :href="$section->ctaUrl() ?? $contactHref" size="lg" arrow>{{ $copy['cta_label'] }}</x-ui.button>
                <x-ui.button :href="route('portfolio.index')" variant="secondary" size="lg">{{ __('Explore Our Work') }}</x-ui.button>
            </div>
            @if ($copy['points'] !== [])
                <ul class="flex flex-col gap-2.5 type-body-sm text-fg-secondary sm:flex-row sm:flex-wrap sm:gap-x-6 sm:gap-y-2">
                    @foreach ($copy['points'] as $point)
                        <li class="flex items-center gap-2"><x-ui.check-circle :size="18" />{{ $point }}</li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="relative mx-auto w-full max-w-[35rem]">
            <div class="aspect-[560/540] overflow-hidden rounded-3xl border border-v2-hairline bg-v2-tint shadow-v2-card lg:rounded-4xl">
                <x-site.image name="hero-glass-shapes" :sizes="\App\Services\SiteImages::HERO_SIZES" eager described
                              class="size-full object-cover motion-safe:animate-[hero-float_6s_ease-in-out_infinite]" />
            </div>
            <a href="{{ route('assistant') }}"
               class="group absolute start-4 bottom-4 flex max-w-[calc(100%-2rem)] items-end gap-2 rounded-2xl sm:start-5 sm:bottom-6 sm:max-w-[23.75rem]">
                <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-brand-600 text-ink-0 shadow-v2-glow" aria-hidden="true"><x-ui.icon name="bot" class="size-4" /></span>
                <span class="flex flex-col gap-1 rounded-2xl rounded-es-sm border border-white/60 bg-white/86 px-3.5 py-2.5 shadow-v2-card backdrop-blur-[16px] transition duration-200 ease-out group-hover:-translate-y-0.5 group-hover:shadow-v2-lift">
                    <span class="sr-only">{{ __('Chat with our AI assistant') }}:</span>
                    <span class="type-body-sm text-ink-900">{{ __('Great! Which platforms do you need — web, mobile, or both?') }}</span>
                    <span class="type-body-xs text-ink-600" dir="ltr" aria-hidden="true">10:42</span>
                </span>
            </a>
        </div>
    </div>
</section>
