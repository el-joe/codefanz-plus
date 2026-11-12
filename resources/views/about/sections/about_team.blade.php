@php($icons = ['palette', 'server', 'monitor', 'shield', 'bot', 'briefcase', 'cloud', 'database'])
<section class="bg-subtle" aria-labelledby="{{ $headingId }}" data-section="about_team">
    <div class="mx-auto flex max-w-7xl flex-col gap-10 px-5 py-16 lg:px-20 lg:py-24">
        <x-site.section-heading :id="$headingId" :eyebrow="$copy['eyebrow']" :heading="$copy['heading']" :subheading="$copy['subheading']" />
        <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($copy['items'] as $discipline)
                <li class="flex items-center gap-4 rounded-xl border border-line bg-surface p-4">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-brand-subtle text-fg-brand"><x-ui.icon :name="$icons[$loop->index % count($icons)]" /></span>
                    <div class="flex flex-col">
                        <h3 class="text-base font-semibold text-fg">{{ $discipline['title'] }}</h3>
                        @if (filled($discipline['text'] ?? null))
                            <p class="text-sm text-fg-secondary">{{ $discipline['text'] }}</p>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    </div>
</section>
