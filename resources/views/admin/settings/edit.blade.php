@php
    $groupLabels = ['general' => __('General'), 'contact' => __('Contact'), 'integrations' => __('Integrations'), 'seo' => __('SEO'), 'payments' => __('Payments'), 'ai' => __('AI limits')];
    $languages = \App\Models\Language::active();
@endphp

<x-layouts.admin :title="__('Settings')" :crumb="__('System')">
    <nav class="flex flex-wrap gap-2" aria-label="{{ __('Settings sections') }}">
        @foreach ($groups as $name)
            <a href="{{ route('admin.settings.edit', $name) }}" @if ($group === $name) aria-current="page" @endif
               class="rounded-md px-3 py-1.5 text-sm font-semibold {{ $group === $name ? 'bg-surface text-fg shadow-xs ring-1 ring-line' : 'text-fg-secondary hover:bg-subtle' }}">{{ $groupLabels[$name] ?? \Illuminate\Support\Str::headline($name) }}</a>
        @endforeach
    </nav>

    @if ($errors->any())
        <x-ui.alert tone="danger" :title="__('Please fix the highlighted fields')">{{ __('Some fields need attention.') }}</x-ui.alert>
    @endif

    @if ($integrations !== [])
        <div class="grid gap-4 md:grid-cols-2">
            @foreach ($integrations as $integration)
                <x-ui.card>
                    <div class="flex items-center justify-between gap-3">
                        <p class="font-semibold text-fg" dir="auto">{{ __($integration['label']) }}</p>
                        @if ($integration['configured'])
                            <x-ui.badge tone="info">{{ __('Credentials saved · not verified') }}</x-ui.badge>
                        @elseif ($integration['partial'])
                            <x-ui.badge tone="warning">{{ __('Incomplete') }}</x-ui.badge>
                        @else
                            <x-ui.badge>{{ __('Requires credentials') }}</x-ui.badge>
                        @endif
                    </div>
                    @if ($integration['selected'])
                        <p class="mt-2 text-sm font-semibold text-fg-brand" data-ai-provider-selected>{{ __('Selected AI provider') }}{{ $integration['configured'] ? '' : ' · '.__('the assistant uses its guided questionnaire until a key is saved') }}</p>
                    @endif
                    <p class="mt-2 text-xs text-fg-tertiary">{{ __('Connection tests arrive with each integration. Until then a status never claims to be live.') }}</p>
                </x-ui.card>
            @endforeach
        </div>
    @endif

    @if ($paymentGateways !== [])
        @include('admin.settings._payment-gateways')
    @else
        <form method="POST" action="{{ route('admin.settings.update', $group) }}" class="flex max-w-3xl flex-col gap-6">
            @csrf
            @method('PUT')

            <x-ui.card>
                <div class="flex flex-col gap-5">
                    @include('admin.settings._fields', ['fields' => $fields])
                </div>
            </x-ui.card>

            <div><x-ui.button>{{ __('Save changes') }}</x-ui.button></div>
        </form>
    @endif
</x-layouts.admin>
