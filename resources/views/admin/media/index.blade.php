<x-layouts.admin :title="__('Media library')" :crumb="__('Content & SEO')">
    @if ($errors->any())
        <x-ui.alert tone="danger" :title="__('Upload failed')">
            <ul class="list-disc ps-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </x-ui.alert>
    @endif

    <div class="flex flex-wrap items-end gap-4">
        <nav class="flex flex-1 flex-wrap gap-2" aria-label="{{ __('Filter media') }}">
            @foreach (['' => __('All'), 'unused' => __('Unused'), 'failed' => __('Failed')] as $value => $label)
                <a href="{{ route('admin.media.index', $value ? ['filter' => $value] : []) }}" @if ($filter === $value) aria-current="page" @endif
                   class="rounded-md px-3 py-1.5 text-sm font-semibold {{ $filter === $value ? 'bg-surface text-fg shadow-xs ring-1 ring-line' : 'text-fg-secondary hover:bg-subtle' }}">{{ $label }}</a>
            @endforeach
        </nav>
        <form method="POST" action="{{ route('admin.media.store') }}" enctype="multipart/form-data" class="flex min-w-0 max-w-full flex-wrap items-center gap-3">
            @csrf
            <label for="files" class="sr-only">{{ __('Files') }}</label>
            <input id="files" type="file" name="files[]" multiple required accept="image/jpeg,image/png,image/webp,image/gif,image/avif"
                   class="w-full min-w-0 text-sm sm:w-auto text-fg-secondary file:me-3 file:rounded-md file:border-0 file:bg-brand-subtle file:px-3 file:py-2 file:font-semibold file:text-fg-brand">
            <x-ui.button size="sm">{{ __('Upload') }}</x-ui.button>
        </form>
    </div>
    <p class="text-xs text-fg-tertiary">{{ __('JPG, PNG, WebP, GIF or AVIF · up to :size MB · converted to AVIF with WebP fallback in responsive sizes. Animated images are kept as-is. SVG uploads are disabled until an SVG sanitizer is approved.', ['size' => intdiv(config('codefanz.media.max_upload_kb'), 1024)]) }}</p>

    @if ($media->isEmpty())
        <x-ui.card><x-ui.empty-state :title="__('No files yet')" :description="__('Uploaded images appear here once they are optimized.')" /></x-ui.card>
    @else
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @foreach ($media as $item)
                <a href="{{ route('admin.media.show', $item) }}" class="overflow-hidden rounded-xl border border-line bg-surface hover:border-line-brand">
                    <div class="flex aspect-video items-center justify-center bg-subtle">
                        @if ($item->status === \App\Enums\MediaStatus::Ready)
                            <x-media.picture :media="$item" sizes="320px" class="h-full w-full object-cover" />
                        @else
                            <x-ui.badge :tone="$item->status->tone()">{{ __(ucfirst($item->status->value)) }}</x-ui.badge>
                        @endif
                    </div>
                    <div class="flex flex-col gap-1.5 p-3">
                        <p class="truncate text-sm font-semibold text-fg" dir="ltr">{{ $item->original_name }}</p>
                        <p class="text-xs text-fg-tertiary" dir="ltr">{{ $item->width }}×{{ $item->height }} · {{ \Illuminate\Support\Number::fileSize($item->size) }}</p>
                        <p class="text-xs {{ $item->usages_count ? 'text-fg-secondary' : 'text-fg-warning' }}">{{ $item->usages_count ? trans_choice('Used in :count place|Used in :count places', $item->usages_count) : __('Unused') }}</p>
                    </div>
                </a>
            @endforeach
        </div>
        {{ $media->links() }}
    @endif
</x-layouts.admin>
