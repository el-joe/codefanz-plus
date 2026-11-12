{{-- Ordered list of library images (gallery, screenshots). Submits $name[] in DOM order; blank rows are ignored. Works without JS. --}}
@php
    $options = $images->mapWithKeys(fn ($image) => [$image->id => $image->original_name])->all();
    $row = function (?int $selectedId) use ($name, $options, $label): string {
        return view('admin.portfolio._media-row', ['name' => $name, 'options' => $options, 'selected' => $selectedId, 'label' => $label])->render();
    };
@endphp

<x-ui.card :title="$title">
    <div data-repeater class="flex flex-col gap-4">
        <p class="text-sm text-fg-secondary">{{ $description }}</p>
        @error($name)<p class="text-sm text-fg-danger">{{ $message }}</p>@enderror
        @foreach ($errors->get($name.'.*') as $messages)
            <p class="text-sm text-fg-danger">{{ $messages[0] }}</p>
        @endforeach
        @if ($images->isEmpty())
            <p class="text-sm text-fg-secondary">{{ __('No optimized images yet. Upload images in the media library.') }}</p>
        @else
            <ol data-repeater-list class="flex flex-col gap-2">
                @foreach ($selected as $mediaId)
                    {!! $row((int) $mediaId) !!}
                @endforeach
                {!! $row(null) !!}
            </ol>
            <template data-repeater-template>{!! $row(null) !!}</template>
            <div>
                <x-ui.button type="button" variant="secondary" size="sm" data-repeater-add hidden>{{ __('Add image') }}</x-ui.button>
            </div>
        @endif
    </div>
</x-ui.card>
