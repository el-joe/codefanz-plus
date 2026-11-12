{{-- Studio status strip (B01): provider, automation switch, spend against the cap, private-until-approved note. --}}
@php($usd = fn (float $minor): string => '$'.number_format($minor / 100, 2))
<div class="flex flex-wrap items-center gap-x-6 gap-y-2 rounded-xl border border-line bg-brand-subtle px-5 py-4 text-sm" data-studio-banner>
    <span class="font-semibold text-fg" dir="auto">
        {{ __('Provider') }}: {{ $provider->isConfigured() ? $provider->name().' · '.$provider->model() : __('Not configured') }}
    </span>
    @if ($automationPaused)
        <x-ui.badge tone="danger">{{ __('AI automation paused') }}</x-ui.badge>
    @else
        <x-ui.badge tone="success">{{ __('AI automation on') }}</x-ui.badge>
    @endif
    <span class="ms-auto text-fg-secondary" dir="auto">{{ __('Usage this month: :spent of :cap cap', ['spent' => $usd($spentMinor), 'cap' => $usd($limitMinor)]) }}</span>
    <span class="font-semibold text-fg-success">{{ __('Drafts are private until approved') }}</span>
</div>
@if ($studioReason)
    <x-ui.alert tone="warning" :title="__('AI generation is unavailable')" data-studio-unavailable>{{ $studioReason->label() }}</x-ui.alert>
@endif
