{{-- Figma "Hero": copy column + product visual (chart card, KPI card and assistant bubble drawn in HTML/CSS). --}}
<section class="bg-canvas bg-[radial-gradient(50rem_16.7rem_at_5rem_71%,rgb(110_86_255/0.18),transparent)] rtl:bg-[radial-gradient(50rem_16.7rem_at_calc(100%-5rem)_71%,rgb(110_86_255/0.18),transparent)]" aria-labelledby="{{ $headingId }}" data-section="hero">
    <div class="container-page grid grid-cols-1 items-center gap-10 pt-10 pb-14 lg:pt-24 lg:pb-28 xl:grid-cols-[minmax(0,1fr)_0.85fr] xl:gap-16">
        <div class="flex flex-col items-start gap-5 lg:gap-7">
            @if ($copy['eyebrow'])
                <x-ui.badge tone="accent" class="w-fit">{{ $copy['eyebrow'] }}</x-ui.badge>
            @endif
            <h1 id="{{ $headingId }}" class="type-h1 text-fg md:type-display">
                @if ($copy['is_default_heading'])
                    {{ __('Turning Ideas Into') }} <span class="text-fg-brand">{{ __('Powerful Digital Products') }}</span>
                @else
                    {{ $copy['heading'] }}
                @endif
            </h1>
            @if ($copy['subheading'])
                <p class="max-w-[41rem] type-body-md text-fg-secondary lg:type-body-lg">{{ $copy['subheading'] }}</p>
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

        {{-- Decorative product visual. Figures are drawn as skeleton bars: numbers on the site only ever come from the admin. --}}
        <div class="@container mx-auto w-full max-w-[35rem]" aria-hidden="true">
            <div class="illustration-stage relative aspect-[560/540] overflow-hidden rounded-[1.75em] bg-[linear-gradient(136deg,var(--color-brand-900)_18.75%,var(--color-ink-950)_81.25%)] text-ink-900">
                <div class="absolute inset-0 bg-[radial-gradient(closest-side,rgb(26_213_200/0.35),transparent)]"></div>

                {{-- Chart card --}}
                <div class="absolute start-[2em] top-[7.5em] flex w-[29.75em] flex-col gap-[0.85em] rounded-[1.0625em] border border-ink-200 bg-ink-0 p-[1.0625em]">
                    <div class="flex items-center gap-[0.5em]">
                        <div class="flex min-w-0 flex-1 flex-col gap-[0.1em]">
                            <span class="truncate text-[0.95625em] leading-[1.45] font-semibold">{{ __('Revenue collected') }}</span>
                            <span class="truncate text-[0.6375em] leading-[1.5] text-ink-500">{{ __('Verified gateway payments, net of refunds') }}</span>
                        </div>
                        <span class="flex shrink-0 gap-[0.1em] rounded-[0.53em] bg-ink-100 p-[0.1em] text-[0.6375em] leading-[1.33] font-semibold text-ink-600" dir="ltr">
                            <span class="px-[0.83em] py-[0.33em]">7D</span><span class="px-[0.83em] py-[0.33em]">30D</span><span class="rounded-[0.5em] bg-ink-0 px-[0.83em] py-[0.33em] text-ink-900 shadow-xs">12M</span>
                        </span>
                    </div>
                    <span class="block h-[1.9em] py-[0.35em]"><span class="block h-full w-[8em] rounded-[0.3em] bg-ink-200"></span></span>
                    <div class="relative h-[8.5em]" dir="ltr">
                        @foreach ([0, 25, 50, 75] as $line)
                            <span class="absolute inset-x-0 h-px bg-ink-100" style="top: {{ $line }}%"></span>
                        @endforeach
                        <div class="relative flex h-full items-end justify-between">
                            @foreach ([37, 48, 42, 53, 51, 63, 58, 70, 65, 77, 81, 88] as $height)
                                <span @class(['w-[6.56%] rounded-t-[0.32em]', 'bg-brand-600/85' => ! $loop->last, 'bg-accent-400' => $loop->last]) style="height: {{ $height }}%"></span>
                            @endforeach
                        </div>
                    </div>
                    <div class="flex justify-between text-[0.6375em] leading-[1.5] text-ink-500" dir="ltr">
                        @php($months = collect(range(11, 0))->map(fn (int $back): string => now()->startOfMonth()->subMonths($back)->locale(app()->getLocale())->translatedFormat('M')))
                        @php($sparse = $months->contains(fn (string $name): bool => mb_strlen($name) > 4))
                        @foreach ($months as $name)
                            {{-- Long month names (Arabic) label every other bar so the axis never overlaps. --}}
                            <span class="w-[6.56%] text-center whitespace-nowrap">{{ $sparse && $loop->odd ? '' : $name }}</span>
                        @endforeach
                    </div>
                </div>

                {{-- KPI card --}}
                <div class="absolute start-[18.2em] top-[1.75em] flex w-[15.3em] flex-col gap-[0.9em] rounded-[1.125em] border border-ink-200 bg-ink-0 p-[1.125em] shadow-[0_1.35em_1.35em_rgb(20_24_38/0.14)]">
                    <div class="flex items-center gap-[0.5em]">
                        <span class="min-w-0 flex-1 truncate text-[0.7875em] leading-[1.43] font-semibold text-ink-600">{{ __('New leads') }}</span>
                        <span class="flex size-[2.025em] items-center justify-center rounded-[0.7875em] bg-brand-50 text-brand-600"><x-ui.icon name="users" class="size-[1.0125em]" /></span>
                    </div>
                    <span class="block h-[2.475em] py-[0.45em]"><span class="block h-full w-[4.5em] rounded-[0.35em] bg-ink-200"></span></span>
                    <span class="flex items-center gap-[0.45em]">
                        <span class="block h-[0.9em] w-[2.6em] rounded-full bg-success-50"></span>
                        <span class="text-[0.675em] leading-[1.5] text-ink-500">{{ __('vs last month') }}</span>
                    </span>
                </div>

                {{-- Assistant bubble --}}
                <div class="absolute start-[1.25em] top-[26.8em] flex w-[23.75em] items-end gap-[0.475em]">
                    <span class="flex size-[1.6625em] shrink-0 items-center justify-center rounded-full bg-brand-600 text-ink-0"><x-ui.icon name="bot" class="size-[0.95em]" /></span>
                    <span class="flex flex-col gap-[0.2375em] rounded-[0.95em] rounded-es-[0.2375em] bg-ink-0 px-[0.7125em] py-[0.59em] shadow-lg">
                        <span class="text-[0.83125em] leading-[1.57]">{{ __('Great! Which platforms do you need — web, mobile, or both?') }}</span>
                        <span class="text-[0.7125em] leading-[1.5] text-ink-500" dir="ltr">10:42</span>
                    </span>
                </div>
            </div>
        </div>
    </div>
</section>
