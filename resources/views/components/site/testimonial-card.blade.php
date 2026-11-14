{{-- Figma "Card/Testimonial". Expects translations and avatar.variants loaded; only pass publicly visible (consented) testimonials. --}}
@props(['testimonial', 'headingTag' => 'p'])

@php
    $quote = $testimonial->exactTranslation(app()->getLocale());
    $avatar = $testimonial->avatarImage();
    $byline = collect([$quote?->author_role, $testimonial->company])->filter()->implode(' · ');
@endphp

<li {{ $attributes->merge(['class' => 'flex flex-col rounded-3xl border border-v2-hairline bg-surface p-6 shadow-v2-card']) }}>
    <figure class="flex h-full flex-col gap-5">
        <div class="flex items-start justify-between gap-4">
            <span class="h-10 font-sans text-[3.5rem] leading-[1] font-extrabold text-fg-brand rtl:-scale-x-100" aria-hidden="true">&ldquo;</span>
            @if ($testimonial->rating)
                <p class="flex gap-0.5" aria-label="{{ __('Rated :rating out of 5', ['rating' => $testimonial->rating]) }}">
                    @for ($star = 1; $star <= 5; $star++)
                        <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" class="{{ $star <= $testimonial->rating ? 'fill-current text-fg-warning' : 'fill-current text-line' }}"><path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9z"/></svg>
                    @endfor
                </p>
            @endif
        </div>
        <blockquote class="flex-1 type-body-md text-fg">
            <p>{{ $quote?->quote }}</p>
        </blockquote>
        <figcaption class="flex items-center gap-3">
            @if ($avatar)
                <x-media.picture :media="$avatar" sizes="40px" :alt="''" class="size-10 rounded-full object-cover" />
            @else
                <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-brand-subtle type-label-md text-fg-brand" aria-hidden="true">{{ $testimonial->initials() }}</span>
            @endif
            <span class="flex min-w-0 flex-col">
                <{{ $headingTag }} class="type-label-md text-fg" dir="auto">{{ $testimonial->author_name }}</{{ $headingTag }}>
                @if ($byline !== '')
                    <span class="type-body-xs text-fg-tertiary" dir="auto">{{ $byline }}</span>
                @endif
            </span>
        </figcaption>
    </figure>
    {{ $slot }}
</li>
