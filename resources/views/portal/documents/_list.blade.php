{{-- Client-visible documents grouped by title: latest version first, earlier versions in a disclosure. --}}
<ul class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3" data-documents>
    @foreach ($groups as $versions)
        @php($document = $versions->first())
        <li class="flex flex-col gap-3 rounded-xl border border-line bg-surface p-4" data-document="{{ $document->uuid }}">
            <div class="flex items-start gap-3">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-brand-subtle text-fg-brand" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>
                </span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold text-fg" dir="auto">{{ $document->title }}</p>
                    <p class="text-xs text-fg-tertiary">{{ $document->category->label() }} · v{{ $document->version }} · {{ $document->humanSize() }}</p>
                </div>
                <a href="{{ route('portal.documents.download', $document) }}" class="flex size-9 shrink-0 items-center justify-center rounded-md text-fg-secondary hover:bg-subtle" aria-label="{{ __('Download :title', ['title' => $document->title]) }}">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/><path d="M12 15V3"/></svg>
                </a>
            </div>
            <p class="text-xs text-fg-tertiary">
                {{ $document->uploaded_by_client ? __('Shared by you') : __('Shared by CodeFanz') }} · {{ $document->created_at->translatedFormat('j M Y') }}
                @if ($showProject ?? true) · <span dir="auto">{{ $document->project->name }}</span>@endif
            </p>
            @if ($versions->count() > 1)
                <details class="text-xs">
                    <summary class="cursor-pointer font-semibold text-fg-brand">{{ __('Earlier versions (:count)', ['count' => $versions->count() - 1]) }}</summary>
                    <ul class="mt-2 flex flex-col gap-1">
                        @foreach ($versions->skip(1) as $older)
                            <li><a href="{{ route('portal.documents.download', $older) }}" class="text-fg-secondary hover:text-fg-brand">v{{ $older->version }} · {{ $older->created_at->translatedFormat('j M Y') }} · {{ $older->humanSize() }}</a></li>
                        @endforeach
                    </ul>
                </details>
            @endif
        </li>
    @endforeach
</ul>
