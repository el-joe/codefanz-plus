@props(['title', 'description' => null])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center gap-2 px-6 py-12 text-center']) }}>
    <p class="text-base font-semibold text-fg">{{ $title }}</p>
    @if ($description)
        <p class="max-w-md text-sm text-fg-secondary">{{ $description }}</p>
    @endif
    {{ $slot }}
</div>
