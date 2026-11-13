{{--
    Figma "Our Story": copy beside the gradient visual with up to two floating stat cards (published statistics).
    The visual scales with its container (1em = 16 design px at 520px wide, never below 12px) and mirrors in RTL.
--}}
@php
    $statIcons = [
        ['icon' => 'users', 'tile' => 'bg-brand-subtle text-icon-brand'],
        ['icon' => 'credit-card', 'tile' => 'bg-warning-subtle text-fg-warning'],
    ];
    $statPositions = ['start-[7.7%] top-[12%]', 'start-[40%] top-[44.5%]'];
@endphp
<x-site.section :tone="$tone" inner="grid gap-12 lg:grid-cols-[1fr_32.5rem] lg:items-center lg:gap-20" aria-labelledby="{{ $headingId }}" data-section="about_story">
    <div @class(['flex flex-col gap-5', 'lg:col-span-2 lg:max-w-3xl' => $statistics->isEmpty()])>
        <x-site.section-heading :id="$headingId" align="start" :eyebrow="$copy['eyebrow']" :heading="$copy['heading']" :subheading="$copy['subheading']" />
    </div>
    @if ($statistics->isNotEmpty())
        <div class="@container">
            <div class="relative aspect-[13/10] overflow-hidden rounded-[1.5rem] bg-[linear-gradient(135deg,var(--color-brand-600),var(--color-accent-500))] [font-size:max(calc(100cqw/32.5),0.75rem)] rtl:bg-[linear-gradient(225deg,var(--color-brand-600),var(--color-accent-500))]">
                <dl>
                    @foreach ($statistics->take(2) as $statistic)
                        @php($translation = $statistic->exactTranslation(app()->getLocale()))
                        <div class="absolute flex w-[52.3%] flex-col gap-[1em] rounded-[1.25em] border border-line bg-surface p-[1.25em] shadow-lg {{ $statPositions[$loop->index] }}">
                            <dt class="flex items-center justify-between gap-[0.75em]">
                                <span class="min-w-0 text-[0.875em] leading-[1.43] font-semibold text-fg-secondary">{{ $translation->label }}</span>
                                <span class="flex size-[2.25em] shrink-0 items-center justify-center rounded-full {{ $statIcons[$loop->index]['tile'] }}" aria-hidden="true"><x-ui.icon :name="$statIcons[$loop->index]['icon']" class="size-[1.125em]" /></span>
                            </dt>
                            <dd class="text-[2.25em] leading-[1.22] font-bold text-fg"><span dir="ltr">{{ $statistic->formattedValue() }}{{ $translation->suffix }}</span></dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        </div>
    @endif
</x-site.section>
