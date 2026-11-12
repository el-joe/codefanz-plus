<section class="bg-subtle" aria-labelledby="{{ $headingId }}" data-section="about_mission">
    <div class="mx-auto flex max-w-4xl flex-col items-center gap-3 px-5 py-14 text-center lg:py-20">
        @if ($copy['eyebrow'])
            <p class="text-xs font-bold uppercase tracking-wider text-fg-brand">{{ $copy['eyebrow'] }}</p>
        @endif
        <h2 id="{{ $headingId }}" class="text-2xl font-bold leading-snug tracking-tight text-fg lg:text-3xl">{{ $copy['heading'] }}</h2>
        @if ($copy['subheading'])
            <p class="max-w-2xl text-lg text-fg-secondary">{{ $copy['subheading'] }}</p>
        @endif
    </div>
</section>
