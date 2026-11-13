{{--
    Figma "mockup" placeholder for projects/products without screenshots: a gradient stage with a white app window
    (sidebar, stat tiles and a bar chart, or table rows) drawn in SVG. The window always covers 80% of the stage width,
    10% from the sides and 14% from the top, so give the stage the same aspect ratio as `window` (e.g. aspect-[32/15]
    for 1024×480). Decorative only (aria-hidden). In RTL the sidebar moves to the right while the chart still rises left to right, like the Arabic frames.

    tone: brand-accent | brand | teal | ink | brand-teal | deep · layout: chart | table · window: [width, height] in design px
--}}
@props(['tone' => 'brand-accent', 'layout' => 'chart', 'window' => [1024, 480]])

@php
    $gradients = [
        'brand-accent' => 'bg-[linear-gradient(135deg,var(--color-brand-600)_12.5%,var(--color-accent-400)_75%)]',
        'brand' => 'bg-[linear-gradient(135deg,var(--color-brand-800),var(--color-brand-500))]',
        'teal' => 'bg-[linear-gradient(135deg,var(--color-accent-900),var(--color-accent-400))]',
        'ink' => 'bg-[linear-gradient(135deg,var(--color-ink-900),var(--color-brand-600))]',
        'brand-teal' => 'bg-[linear-gradient(135deg,var(--color-brand-700),var(--color-accent-500))]',
        'deep' => 'bg-[linear-gradient(135deg,var(--color-brand-800),var(--color-accent-500))]',
    ];
    [$width, $height] = $window;
    $clipId = 'screen-mockup-'.\Illuminate\Support\Str::random(8);
    $sidebar = (int) round($width * 0.18);
    $navWidth = (int) round($sidebar * 0.6);
    $contentStart = $sidebar + 18;
    $contentWidth = $width - 18 - $contentStart;
    $tileHeight = (int) round($height * 0.16);
    $tileWidth = ($contentWidth - 24) / 3;
    $barGap = 8;
    $barWidth = ($contentWidth - 11 * $barGap) / 12;
    $baseline = $height - 20;
    $rowHeight = round($height * 0.1);
    $isRtl = (\App\Models\Language::active()[app()->getLocale()]['direction'] ?? 'ltr') === 'rtl';
    $flip = fn (float $x, float $boxWidth): float => $isRtl ? $width - $x - $boxWidth : $x;
    $barStart = $isRtl ? 18 : $contentStart;
@endphp

<div {{ $attributes->merge(['class' => 'relative overflow-hidden '.($gradients[$tone] ?? $gradients['brand-accent'])]) }} aria-hidden="true">
    <svg viewBox="0 0 {{ $width }} {{ $height }}" fill="none" focusable="false" class="pointer-events-none absolute start-[10%] top-[14%] h-auto w-[80%] drop-shadow-[0_24px_24px_rgb(20_24_38/0.14)]">
        <defs><clipPath id="{{ $clipId }}"><rect width="{{ $width }}" height="{{ $height }}" rx="12"/></clipPath></defs>
        <g clip-path="url(#{{ $clipId }})">
            <rect width="{{ $width }}" height="{{ $height }}" class="fill-ink-0"/>
            <rect width="{{ $width }}" height="28" class="fill-ink-50"/>
            <rect x="{{ $flip(0, $sidebar) }}" y="28" width="{{ $sidebar }}" height="{{ $height - 28 }}" class="fill-ink-50"/>
            @for ($item = 0; $item < 5; $item++)
                <rect x="{{ $flip(14, $navWidth) }}" y="{{ 48 + $item * 20 }}" width="{{ $navWidth }}" height="7" rx="3" class="{{ $item === 0 ? 'fill-brand-600' : 'fill-ink-100' }}"/>
            @endfor
            @if ($layout === 'table')
                @for ($row = 0; $row < 5; $row++)
                    <rect x="{{ $flip($contentStart, $contentWidth) }}" y="{{ 46 + $row * ($rowHeight + 10) }}" width="{{ $contentWidth }}" height="{{ $rowHeight }}" rx="6" class="{{ $row === 0 ? 'fill-brand-50' : 'fill-ink-50' }}"/>
                @endfor
            @else
                @for ($tile = 0; $tile < 3; $tile++)
                    <rect x="{{ $flip($contentStart + $tile * ($tileWidth + 12), $tileWidth) }}" y="46" width="{{ $tileWidth }}" height="{{ $tileHeight }}" rx="8" class="fill-ink-50"/>
                @endfor
                @for ($bar = 0; $bar < 12; $bar++)
                    @php($barHeight = $height * (0.135 + $bar * 0.0246))
                    <rect x="{{ $barStart + $bar * ($barWidth + $barGap) }}" y="{{ $baseline - $barHeight }}" width="{{ $barWidth }}" height="{{ $barHeight }}" rx="4" opacity="0.85" class="{{ $bar === 11 ? 'fill-accent-400' : 'fill-brand-600' }}"/>
                @endfor
            @endif
        </g>
    </svg>
</div>
