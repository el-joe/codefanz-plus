{{--
    Figma "Card/Industry" layout used as a compact tile: 44px accent icon tile, title and a one-line note.
    v2: radius 24, hairline, card shadow, gradient icon tile. With an href the whole tile is one link (hover = lift). Used for specialist services, team disciplines and industries.
--}}
@props(['icon' => null, 'title', 'text' => null, 'href' => null, 'headingTag' => 'h3'])

<li {{ $attributes->class(['relative flex min-w-0 items-center gap-3 rounded-3xl border border-v2-hairline bg-surface p-4 shadow-v2-card', 'group transition duration-200 ease-out hover:-translate-y-1 hover:border-brand-600/30 hover:shadow-v2-lift' => $href]) }}>
    <span class="flex size-11 shrink-0 items-center justify-center rounded-lg tile-v2 text-icon-brand" aria-hidden="true"><x-ui.icon :name="$icon ?? 'briefcase'" :size="22" /></span>
    <span class="flex min-w-0 flex-1 flex-col gap-0.5">
        <{{ $headingTag }} class="type-label-lg text-fg">
            @if ($href)
                <a href="{{ $href }}" class="after:absolute after:inset-0 after:rounded-3xl focus-visible:outline-none focus-visible:after:outline-2 focus-visible:after:outline-offset-2 focus-visible:after:outline-line-focus">{{ $title }}</a>
            @else
                {{ $title }}
            @endif
        </{{ $headingTag }}>
        @if (filled($text))
            <span class="truncate type-body-xs text-fg-tertiary">{{ $text }}</span>
        @endif
    </span>
</li>
