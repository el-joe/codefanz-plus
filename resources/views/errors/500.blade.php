{{-- Must not touch the database or the session: reference id from the log context, support email from config. --}}
@php
    \App\Services\ErrorPageLocale::apply(request());
    $reference = \App\Http\Middleware\AssignRequestId::current();
    $support = config('codefanz.support_email');
    $retry = request()->isMethod('GET') ? request()->fullUrl() : null;
@endphp
<x-layouts.error code="500" tone="danger" :title="__('Something went wrong on our side')" :message="__('Our team has been notified automatically. Please try again in a moment.')">
    <div class="flex flex-wrap justify-center gap-3">
        @if ($retry)
            <x-ui.button :href="$retry">{{ __('Try again') }}</x-ui.button>
        @endif
        <x-ui.button :href="route('home', ['locale' => app()->getLocale()])" variant="secondary">{{ __('Go to homepage') }}</x-ui.button>
    </div>
    <p class="flex flex-wrap items-center justify-center gap-2 rounded-md bg-subtle px-3 py-1.5 text-sm text-fg-secondary" data-error-reference>
        {{ __('Reference') }} <code class="font-mono text-fg" dir="ltr">{{ $reference }}</code>
    </p>
    @if ($support)
        <p class="text-sm text-fg-secondary">{{ __('If this keeps happening, email :email with the reference.', ['email' => $support]) }}</p>
    @endif
</x-layouts.error>
