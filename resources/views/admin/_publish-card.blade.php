{{-- Shared publishing sidebar card: status, save button, publish toggle and optional live link. --}}
@php($isPublished = $model->status === \App\Enums\ContentStatus::Published)

<x-ui.card :title="__('Publishing')">
    <div class="flex flex-col gap-4">
        @if ($model->exists)
            <p class="text-sm text-fg-secondary">{{ __('Status') }}: <x-ui.badge :tone="$isPublished ? 'success' : 'neutral'">{{ $model->status->label() }}</x-ui.badge></p>
        @endif
        {{ $slot ?? '' }}
        <x-ui.button :form="$form">{{ $model->exists ? __('Save changes') : __('Create draft') }}</x-ui.button>
        @if ($model->exists)
            <form method="POST" action="{{ $publishUrl }}">
                @csrf
                <x-ui.button variant="secondary" class="w-full">{{ $isPublished ? __('Unpublish') : __('Publish') }}</x-ui.button>
            </form>
            @if ($isPublished && ($liveUrl ?? null))
                <a href="{{ $liveUrl }}" class="text-sm font-semibold text-fg-brand hover:underline" target="_blank" rel="noopener">{{ __('View live page') }}</a>
            @endif
        @endif
    </div>
</x-ui.card>
