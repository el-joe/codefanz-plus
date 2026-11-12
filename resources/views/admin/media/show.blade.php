<x-layouts.admin :title="$media->original_name" :crumb="__('Media')">
    @if ($errors->any())
        <x-ui.alert tone="danger">{{ $errors->first() }}</x-ui.alert>
    @endif

    <div class="grid gap-6 xl:grid-cols-[1fr_380px]">
        <x-ui.card>
            @if ($media->status === \App\Enums\MediaStatus::Ready)
                <x-media.picture :media="$media" sizes="(min-width: 1280px) 800px, 100vw" class="rounded-lg" eager />
            @else
                <x-ui.alert :tone="$media->status === \App\Enums\MediaStatus::Failed ? 'danger' : 'info'" :title="__(ucfirst($media->status->value))">
                    {{ $media->error ?? __('Optimization is running. Refresh in a moment.') }}
                </x-ui.alert>
            @endif
        </x-ui.card>

        <div class="flex flex-col gap-6">
            <x-ui.card :title="__('Details')">
                <form method="POST" action="{{ route('admin.media.update', $media) }}" class="flex flex-col gap-4">
                    @csrf
                    @method('PATCH')
                    @foreach (\App\Models\Language::active() as $code => $language)
                        <x-ui.input :name="'alt['.$code.']'" :id="'alt-'.$code" :label="__('Alt text (:language)', ['language' => $language['native_name']])" :value="$media->alt[$code] ?? ''" :dir="$language['direction']" />
                    @endforeach
                    <x-ui.input name="folder" :label="__('Folder')" :value="$media->folder" dir="ltr" />
                    <x-ui.button>{{ __('Save') }}</x-ui.button>
                </form>
            </x-ui.card>

            <x-ui.card :title="__('File')">
                <dl class="flex flex-col gap-2 text-sm" dir="ltr">
                    <div class="flex justify-between gap-4"><dt class="text-fg-secondary">{{ __('Original') }}</dt><dd class="text-fg">{{ $media->mime_type }} · {{ $media->width }}×{{ $media->height }} · {{ \Illuminate\Support\Number::fileSize($media->size) }}</dd></div>
                    @if ($media->deliveredBytes())
                        <div class="flex justify-between gap-4"><dt class="text-fg-secondary">{{ __('Delivered (AVIF)') }}</dt><dd class="text-fg">{{ \Illuminate\Support\Number::fileSize($media->deliveredBytes()) }} ({{ round(100 - $media->deliveredBytes() / max($media->size, 1) * 100) }}% {{ __('smaller') }})</dd></div>
                    @endif
                    <div class="flex justify-between gap-4"><dt class="text-fg-secondary">{{ __('Variants') }}</dt><dd class="text-fg">{{ $media->variants->map(fn ($v) => $v->format.' '.$v->width)->implode(' · ') ?: '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-fg-secondary">{{ __('Uploaded by') }}</dt><dd class="text-fg">{{ $media->uploader?->name ?? '—' }}</dd></div>
                </dl>
            </x-ui.card>

            <x-ui.card :title="__('Used in')">
                @forelse ($media->usages as $usage)
                    <p class="text-sm text-fg">{{ $usage->mediable_type }} #{{ $usage->mediable_id }} · {{ $usage->collection }}</p>
                @empty
                    <p class="text-sm text-fg-warning">{{ __('Not used anywhere.') }}</p>
                    <form method="POST" action="{{ route('admin.media.destroy', $media) }}" class="mt-4" data-confirm="{{ __('Move this file to trash?') }}" data-confirm-label="{{ __('Move to trash') }}">
                        @csrf
                        @method('DELETE')
                        <x-ui.button variant="danger" size="sm">{{ __('Move to trash') }}</x-ui.button>
                    </form>
                @endforelse
            </x-ui.card>
        </div>
    </div>
</x-layouts.admin>
