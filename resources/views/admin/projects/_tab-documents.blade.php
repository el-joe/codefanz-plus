<div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
    <x-ui.card :title="__('Documents')">
        @if ($documents->isEmpty())
            <x-ui.empty-state :title="__('No documents yet')" :description="__('Upload contracts, designs and reports. Choose whether the client can see each file.')" />
        @else
            <ul class="flex flex-col divide-y divide-line-subtle" data-documents>
                @foreach ($documents as $title => $versions)
                    @foreach ($versions as $document)
                        <li class="flex flex-wrap items-center gap-3 py-3 {{ $loop->first ? '' : 'ps-6' }}" data-document="{{ $document->uuid }}" data-visibility="{{ $document->visibility->value }}">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-fg" dir="auto">
                                    <a href="{{ route('admin.documents.download', $document) }}" class="hover:text-fg-brand">{{ $document->title }}</a>
                                    <span class="ms-1 font-mono text-xs text-fg-tertiary">v{{ $document->version }}</span>
                                </p>
                                <p class="text-xs text-fg-tertiary">
                                    {{ $document->category->label() }} · {{ $document->humanSize() }} · <span dir="auto">{{ $document->uploaded_by_client ? __('Shared by client') : ($document->uploader?->name ?? __('Former user')) }}</span> · {{ $document->created_at->translatedFormat('j M Y') }}
                                </p>
                            </div>
                            <x-ui.badge :tone="$document->visibility->tone()">{{ $document->visibility->label() }}</x-ui.badge>
                            @if ($canManage)
                                <form method="POST" action="{{ route('admin.projects.documents.update', [$project, $document]) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="visibility" value="{{ $document->isClientVisible() ? 'internal' : 'client' }}">
                                    <x-ui.button size="sm" variant="secondary">{{ $document->isClientVisible() ? __('Make internal') : __('Share with client') }}</x-ui.button>
                                </form>
                                <form method="POST" action="{{ route('admin.projects.documents.destroy', [$project, $document]) }}" data-confirm="{{ __('Delete :title version :version?', ['title' => $document->title, 'version' => $document->version]) }}" data-confirm-label="{{ __('Delete') }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="rounded-md px-2 py-1 text-sm text-fg-danger hover:bg-subtle">{{ __('Delete') }}</button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                @endforeach
            </ul>
        @endif
    </x-ui.card>

    @if ($canManage)
        <x-ui.card :title="__('Upload a document')">
            <form method="POST" action="{{ route('admin.projects.documents.store', $project) }}" enctype="multipart/form-data" class="flex flex-col gap-4">
                @csrf
                <div class="flex flex-col gap-1.5">
                    <label for="document-file" class="text-sm font-semibold text-fg">{{ __('File') }}</label>
                    <input id="document-file" type="file" name="file" required class="text-sm text-fg" accept="{{ collect(config('codefanz.leads.attachments.extensions'))->map(fn ($extension) => '.'.$extension)->implode(',') }}">
                    @error('file')<p class="text-sm text-fg-danger">{{ $message }}</p>@enderror
                    <p class="text-xs text-fg-tertiary">{{ __('PDF, Office, images or ZIP, up to :size MB. Stored privately.', ['size' => (int) (config('codefanz.leads.attachments.max_kb') / 1024)]) }}</p>
                </div>
                <x-ui.input name="title" :label="__('Title (optional)')" maxlength="160" dir="auto" :helper="__('Use an existing title to upload a new version.')" />
                <x-ui.select name="category" :label="__('Type')" :options="\App\Enums\DocumentCategory::options()" value="other" />
                <x-ui.select name="visibility" :label="__('Who can see it?')" :options="\App\Enums\ClientVisibility::options()" value="internal" />
                <x-ui.button size="sm" class="w-fit">{{ __('Upload') }}</x-ui.button>
            </form>
        </x-ui.card>
    @endif
</div>
