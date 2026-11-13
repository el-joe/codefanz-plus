{{-- Figma "Logo": gradient mark + "codefanz" wordmark (26px at the 40px mark). On dark surfaces "fanz" turns accent. --}}
@props(['mark' => false, 'size' => 32])

<span {{ $attributes->merge(['class' => 'inline-flex items-center']) }} style="gap: {{ round($size * 0.3) }}px" dir="ltr">
    <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 64 64" role="img" aria-label="CodeFanz" class="shrink-0">
        <defs>
            <linearGradient id="cf-mark-{{ $size }}" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0.25" stop-color="#5A3DF5"/>
                <stop offset="0.75" stop-color="#08B8AE"/>
            </linearGradient>
        </defs>
        <rect width="64" height="64" rx="18" fill="url(#cf-mark-{{ $size }})"/>
        <path d="M23 20L12 32L23 44" stroke="#fff" stroke-width="5.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
        <path d="M45 19H37.5C34.4 19 32 21.4 32 24.5V45" stroke="#fff" stroke-width="5.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
        <path d="M32 32H41" stroke="#fff" stroke-width="5.5" stroke-linecap="round"/>
        <circle cx="48" cy="44" r="4" fill="#8DF8EC"/>
    </svg>
    @unless ($mark)
        <span class="font-sans font-extrabold leading-none text-fg" style="font-size: {{ round($size * 0.65, 1) }}px; letter-spacing: -0.03em">code<span class="text-brand-600 dark:text-accent-300">fanz</span></span>
    @endunless
</span>
