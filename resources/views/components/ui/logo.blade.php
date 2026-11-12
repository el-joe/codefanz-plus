@props(['mark' => false, 'size' => 32])

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2.5']) }} dir="ltr">
    <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 64 64" role="img" aria-label="CodeFanz">
        <defs>
            <linearGradient id="cf-mark-{{ $size }}" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0" stop-color="#5A3DF5"/>
                <stop offset="1" stop-color="#08B8AE"/>
            </linearGradient>
        </defs>
        <rect width="64" height="64" rx="18" fill="url(#cf-mark-{{ $size }})"/>
        <path d="M23 20L12 32L23 44" stroke="#fff" stroke-width="5.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
        <path d="M45 19H37.5C34.4 19 32 21.4 32 24.5V45" stroke="#fff" stroke-width="5.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
        <path d="M32 32H41" stroke="#fff" stroke-width="5.5" stroke-linecap="round"/>
        <circle cx="48" cy="44" r="4" fill="#8DF8EC"/>
    </svg>
    @unless ($mark)
        <span class="font-sans text-xl font-extrabold tracking-tight text-fg">code<span class="text-fg-brand">fanz</span></span>
    @endunless
</span>
