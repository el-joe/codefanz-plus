{{-- Segmented tabs (Figma status tabs). Expects $tabs: list of [label, url, active]. --}}
<nav class="flex w-fit max-w-full gap-1 overflow-x-auto rounded-lg bg-subtle p-1" aria-label="{{ $label }}">
    @foreach ($tabs as [$tabLabel, $url, $active])
        <a href="{{ $url }}" @if ($active) aria-current="page" @endif
           class="whitespace-nowrap rounded-md px-3 py-1.5 text-sm font-semibold {{ $active ? 'bg-surface text-fg shadow-sm' : 'text-fg-secondary hover:text-fg' }}">{{ $tabLabel }}</a>
    @endforeach
</nav>
