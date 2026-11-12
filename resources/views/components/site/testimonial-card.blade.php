{{-- Client quote. Expects translations and avatar.variants loaded; only pass publicly visible (consented) testimonials. --}}
@props(['testimonial', 'headingTag' => 'p'])

@php
    $quote = $testimonial->exactTranslation(app()->getLocale());
    $avatar = $testimonial->avatarImage();
    $byline = collect([$quote?->author_role, $testimonial->company])->filter()->implode(' · ');
@endphp

<li {{ $attributes->merge(['class' => 'flex flex-col gap-5 rounded-xl border border-line bg-surface p-6']) }}>
    <figure class="flex h-full flex-col gap-5">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" class="text-fg-brand rtl:-scale-x-100"><path d="M10 7H6a3 3 0 0 0-3 3v7h7v-7H6a1 1 0 0 1 1-1h3zm11 0h-4a3 3 0 0 0-3 3v7h7v-7h-4a1 1 0 0 1 1-1h3z"/></svg>
        @if ($testimonial->rating)
            <p class="flex gap-0.5" aria-label="{{ __('Rated :rating out of 5', ['rating' => $testimonial->rating]) }}">
                @for ($star = 1; $star <= 5; $star++)
                    <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" class="{{ $star <= $testimonial->rating ? 'fill-current text-fg-warning' : 'fill-current text-line' }}"><path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9z"/></svg>
                @endfor
            </p>
        @endif
        <blockquote class="flex-1 text-base leading-relaxed text-fg">
            <p>{{ $quote?->quote }}</p>
        </blockquote>
        <figcaption class="flex items-center gap-3">
            @if ($avatar)
                <x-media.picture :media="$avatar" sizes="40px" :alt="''" class="size-10 rounded-full object-cover" />
            @else
                <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-brand-subtle text-sm font-semibold text-fg-brand" aria-hidden="true">{{ $testimonial->initials() }}</span>
            @endif
            <span class="flex min-w-0 flex-col">
                <{{ $headingTag }} class="text-sm font-semibold text-fg" dir="auto">{{ $testimonial->author_name }}</{{ $headingTag }}>
                @if ($byline !== '')
                    <span class="text-xs text-fg-tertiary" dir="auto">{{ $byline }}</span>
                @endif
            </span>
        </figcaption>
    </figure>
    {{ $slot }}
</li>
