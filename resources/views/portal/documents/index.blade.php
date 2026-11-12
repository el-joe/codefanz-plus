<x-layouts.portal :title="__('Documents')" :crumb="__('Documents')">
    <div class="flex flex-wrap items-center justify-between gap-3">
        @include('portal._tabs', ['label' => __('Filter documents'), 'tabs' => [
            [__('All'), route('portal.documents.index', array_filter(['project' => $filters['project'] ?? null])), empty($filters['category']) && empty($filters['shared'])],
            [__('Contracts'), route('portal.documents.index', array_filter(['category' => 'contract', 'project' => $filters['project'] ?? null])), ($filters['category'] ?? null) === 'contract'],
            [__('Designs'), route('portal.documents.index', array_filter(['category' => 'design', 'project' => $filters['project'] ?? null])), ($filters['category'] ?? null) === 'design'],
            [__('Reports'), route('portal.documents.index', array_filter(['category' => 'report', 'project' => $filters['project'] ?? null])), ($filters['category'] ?? null) === 'report'],
            [__('Shared by you'), route('portal.documents.index', array_filter(['shared' => 'client', 'project' => $filters['project'] ?? null])), ($filters['shared'] ?? null) === 'client'],
        ]])
        <form method="GET" action="{{ route('portal.documents.index') }}" role="search" class="flex gap-2">
            @foreach (['category', 'project', 'shared'] as $keep)
                @if (! empty($filters[$keep]))<input type="hidden" name="{{ $keep }}" value="{{ $filters[$keep] }}">@endif
            @endforeach
            <label for="document-search" class="sr-only">{{ __('Search documents') }}</label>
            <input id="document-search" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('Search documents…') }}" class="w-56 rounded-md border border-line bg-surface px-3 py-2 text-sm text-fg">
        </form>
    </div>

    @if ($groups->isEmpty())
        <x-ui.card padding="p-6">
            @include('portal._empty-module', ['title' => __('No documents yet'), 'description' => __('Contracts, designs and other files shared with you will be available here.'), 'icon' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>'])
        </x-ui.card>
    @else
        @include('portal.documents._list', ['groups' => $groups, 'showProject' => true])
    @endif

    @if ($projects->isNotEmpty())
        <x-ui.card :title="__('Share a file with your team')">
            <form method="POST" action="{{ route('portal.documents.store') }}" enctype="multipart/form-data" class="grid gap-4 md:grid-cols-2" data-document-upload>
                @csrf
                <x-ui.select name="project" :label="__('Project')" :options="$projects->pluck('name', 'ulid')" :value="$filters['project'] ?? null" required />
                <x-ui.select name="category" :label="__('Type')" :options="\App\Enums\DocumentCategory::options()" value="other" />
                <x-ui.input name="title" :label="__('Title (optional)')" maxlength="160" dir="auto" :helper="__('Use the same title as an existing file to upload a new version.')" />
                <div class="flex flex-col gap-1.5">
                    <label for="upload-file" class="text-sm font-semibold text-fg">{{ __('File') }}</label>
                    <input id="upload-file" type="file" name="file" required class="text-sm text-fg" accept="{{ collect(config('codefanz.leads.attachments.extensions'))->map(fn ($extension) => '.'.$extension)->implode(',') }}">
                    @error('file')<p class="text-sm text-fg-danger">{{ $message }}</p>@enderror
                </div>
                <div class="md:col-span-2"><x-ui.button size="sm">{{ __('Upload') }}</x-ui.button></div>
            </form>
        </x-ui.card>
    @endif

    <p class="flex items-center gap-2 text-xs text-fg-tertiary">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" class="text-fg-success"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        {{ __('Files are stored privately and shared only with your project team. Max :size MB per file.', ['size' => (int) (config('codefanz.leads.attachments.max_kb') / 1024)]) }}
    </p>
</x-layouts.portal>
