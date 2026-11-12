{{-- Closing call to action. Until the quote form ships (Milestone 5) it opens an email to the contact address from Settings. --}}
@props(['title' => null, 'lead' => null, 'action' => null, 'href' => null])

@php($email = app(\App\Services\Settings::class)->get('contact', 'email') ?: 'hello@codefanz.com')

<section class="bg-subtle">
    <div class="mx-auto max-w-7xl px-5 py-16 lg:px-20">
        <div class="flex flex-col gap-6 rounded-2xl bg-[linear-gradient(120deg,var(--color-brand-700),var(--color-brand-900)_55%,var(--color-accent-700))] px-6 py-10 text-fg-on-brand sm:px-10 lg:flex-row lg:items-center">
            <div class="flex flex-1 flex-col gap-2">
                <h2 class="text-2xl font-bold lg:text-3xl">{{ $title ?? __('Have an idea? Let’s build it together.') }}</h2>
                <p class="text-fg-on-brand/80">{{ $lead ?? __('Get a free preliminary estimate within 48 hours. No payment required.') }}</p>
            </div>
            <a href="{{ $href ?? 'mailto:'.$email }}" class="inline-flex w-fit items-center gap-2 rounded-md bg-brand px-5 py-3 text-sm font-semibold text-fg-on-brand hover:bg-brand-hover">
                {{ $action ?? __('Start Your Project') }}
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" class="rtl:-scale-x-100"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
            </a>
        </div>
    </div>
</section>
