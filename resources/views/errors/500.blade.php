{{-- Must not touch the database or the session: reference id from the log context, support email from config. --}}
@php
    \App\Services\ErrorPageLocale::apply(request());
    $reference = \App\Http\Middleware\AssignRequestId::current();
    $support = config('codefanz.support_email');
    $retry = request()->isMethod('GET') ? request()->fullUrl() : null;
@endphp
<x-layouts.error code="500" tone="danger" :footer="false" :title="__('Something went wrong on our side')" :message="__('Our team has been notified automatically. Please try again in a moment.')">
    <div class="flex flex-wrap justify-center gap-3">
        @if ($retry)
            <x-ui.button :href="$retry" size="lg">{{ __('Try again') }}</x-ui.button>
        @endif
        <x-ui.button :href="route('home', ['locale' => app()->getLocale()])" variant="secondary" size="lg">{{ __('Go to homepage') }}</x-ui.button>
    </div>
    <p class="flex flex-wrap items-center justify-center gap-2 rounded-md bg-subtle px-3.5 py-2 type-body-sm text-fg-tertiary" data-error-reference>
        {{ __('Reference') }} <code class="font-mono type-label-sm text-fg" dir="ltr">{{ $reference }}</code>
    </p>
    @if ($support)
        <p class="flex items-center gap-2 type-body-sm text-fg-tertiary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="shrink-0 text-icon-secondary"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
            {{ __('If this keeps happening, email :email with the reference.', ['email' => $support]) }}</p>
    @endif
</x-layouts.error>
