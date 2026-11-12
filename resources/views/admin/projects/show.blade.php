@php
    $canManage = auth()->user()->can('update', $project);
    $tabs = [
        'overview' => __('Overview'),
        'milestones' => __('Milestones (:count)', ['count' => $project->milestones->count()]),
        'payments' => __('Payments'),
        'documents' => __('Documents'),
        'conversation' => __('Conversation'),
        'tickets' => __('Tickets'),
    ];
@endphp

<x-layouts.admin :title="$project->reference" :crumb="__('Projects')">
    <x-ui.card>
        <div class="flex flex-wrap items-start gap-4">
            <div class="min-w-0 flex-1">
                <h2 class="text-2xl font-semibold text-fg" dir="auto">{{ $project->name }}</h2>
                <p class="mt-1 flex flex-wrap items-center gap-2 text-sm text-fg-secondary">
                    @can('view', $project->customer)
                        <a href="{{ route('admin.customers.show', $project->customer) }}" class="font-semibold text-fg-brand hover:underline" dir="auto">{{ $project->customer->company_name }}</a>
                    @else
                        <span dir="auto">{{ $project->customer->company_name }}</span>
                    @endcan
                    <x-ui.badge :tone="$project->status->tone()">{{ $project->status->label() }}</x-ui.badge>
                    <x-ui.badge tone="neutral" :dot="false">{{ $project->type->label() }}</x-ui.badge>
                </p>
            </div>
            <a href="{{ route('admin.projects.index') }}" class="text-sm font-semibold text-fg-brand hover:underline">{{ __('Back to projects') }}</a>
            @if ($canManage)
                <x-ui.button variant="secondary" size="sm" :href="route('admin.projects.edit', $project)">{{ __('Edit') }}</x-ui.button>
            @endif
            @isset($linkDialog)
                <x-ui.button size="sm" type="button" data-dialog-open="payment-link-dialog">{{ __('Create payment link') }}</x-ui.button>
            @endisset
        </div>

        <nav class="mt-5 flex flex-wrap gap-1 border-b border-line" aria-label="{{ __('Project sections') }}">
            @foreach ($tabs as $key => $label)
                <a href="{{ route('admin.projects.show', ['project' => $project, 'tab' => $key]) }}" data-tab="{{ $key }}" @if ($tab === $key) aria-current="page" @endif
                   class="-mb-px border-b-2 px-3 py-2 text-sm font-semibold {{ $tab === $key ? 'border-brand text-fg-brand' : 'border-transparent text-fg-secondary hover:text-fg' }}">{{ $label }}</a>
            @endforeach
        </nav>
    </x-ui.card>

    @if (session('createdPaymentLink'))
        <x-ui.alert tone="success" :title="__('Payment link ready')" data-created-link>
            <div class="mt-1 flex flex-wrap items-center gap-3">
                <code class="break-all rounded bg-surface px-2 py-1 font-mono text-xs text-fg" dir="ltr">{{ session('createdPaymentLink') }}</code>
                <x-ui.button size="sm" variant="secondary" type="button" data-copy="{{ session('createdPaymentLink') }}" data-copied-label="{{ __('Copied') }}">{{ __('Copy link') }}</x-ui.button>
            </div>
            <p class="mt-2">{{ __('Send it to the client. They cannot change the amount.') }}</p>
        </x-ui.alert>
    @endif

    @include('admin.projects._tab-'.$tab)

    @isset($linkDialog)
        @include('admin.projects._payment-link-dialog')
    @endisset
</x-layouts.admin>
