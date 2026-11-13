{{--
    Decorative product window drawn in SVG (Figma project/article thumbnail placeholder) for items without a cover image.
    Always aria-hidden; colours come from the token palette.
--}}
<svg viewBox="0 0 384 259" fill="none" aria-hidden="true" focusable="false" {{ $attributes->merge(['class' => 'pointer-events-none']) }}>
    <g style="filter: drop-shadow(0 24px 24px rgb(20 24 38 / 0.14))">
        <rect x="36" y="12" width="312" height="187" rx="10" class="fill-ink-0"/>
        <path d="M46 12h292a10 10 0 0 1 10 10v12H36V22a10 10 0 0 1 10-10Z" class="fill-ink-50"/>
        <circle cx="49.5" cy="23" r="3.5" fill="#D92D20"/>
        <circle cx="60.5" cy="23" r="3.5" fill="#FFFAEB"/>
        <circle cx="71.5" cy="23" r="3.5" fill="#ECFDF3"/>
        <path d="M36 34h62v165H46a10 10 0 0 1-10-10Z" class="fill-ink-50"/>
        <rect x="46" y="48" width="37" height="6" rx="3" class="fill-brand-600"/>
        <rect x="46" y="64" width="37" height="6" rx="3" class="fill-ink-100"/>
        <rect x="46" y="80" width="37" height="6" rx="3" class="fill-ink-100"/>
        <rect x="46" y="96" width="37" height="6" rx="3" class="fill-ink-100"/>
        <rect x="112" y="46" width="69" height="34" rx="6" class="fill-ink-50"/>
        <rect x="189" y="46" width="69" height="34" rx="6" class="fill-ink-50"/>
        <rect x="266" y="46" width="69" height="34" rx="6" class="fill-ink-50"/>
        @foreach ([27, 33, 39, 45, 52, 58, 64] as $index => $height)
            <rect x="{{ 112 + $index * 29 }}" y="{{ 185 - $height }}" width="23" height="{{ $height }}" rx="3" opacity="0.85" class="fill-brand-600"/>
        @endforeach
        <rect x="315" y="115" width="23" height="70" rx="3" opacity="0.85" class="fill-accent-400"/>
    </g>
</svg>
