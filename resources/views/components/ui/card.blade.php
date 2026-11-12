@props(['title' => null, 'padding' => 'p-6'])

<section {{ $attributes->merge(['class' => 'rounded-xl border border-line bg-surface '.$padding]) }}>
    @if ($title || isset($action))
        <div class="mb-4 flex items-center gap-3">
            @if ($title)
                <h2 class="flex-1 text-lg font-semibold text-fg">{{ $title }}</h2>
            @endif
            {{ $action ?? '' }}
        </div>
    @endif
    {{ $slot }}
</section>
