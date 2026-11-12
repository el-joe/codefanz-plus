{{-- A12 · Redirects: managed 301/302/410 with hit counts and CSV import. --}}
<x-layouts.admin :title="__('Redirects')" :crumb="__('Content & SEO')">
    <div class="flex flex-wrap items-center justify-between gap-4">
        @include('admin.seo._tabs')
        <x-ui.button :href="route('admin.redirects.create')">{{ __('New redirect') }}</x-ui.button>
    </div>

    @if (session('importErrors'))
        <x-ui.alert tone="warning" :title="__('Some rows were skipped')">
            <ul class="list-disc ps-5">
                @foreach (session('importErrors') as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-ui.alert>
    @endif

    <div class="grid gap-4 lg:grid-cols-[1fr_360px]">
        <form method="GET" action="{{ route('admin.redirects.index') }}" role="search" class="flex items-end gap-3">
            <div class="flex-1"><x-ui.input name="q" type="search" :label="__('Search paths')" :value="$search" maxlength="100" dir="ltr" /></div>
            <x-ui.button variant="secondary">{{ __('Search') }}</x-ui.button>
        </form>

        <form method="POST" action="{{ route('admin.redirects.import') }}" enctype="multipart/form-data" class="flex flex-col gap-2 rounded-xl border border-line bg-surface p-4">
            @csrf
            <label for="redirects-file" class="text-sm font-semibold text-fg">{{ __('Import CSV') }}</label>
            <input id="redirects-file" type="file" name="file" accept=".csv,text/csv" required class="text-sm text-fg-secondary" @error('file') aria-invalid="true" aria-describedby="redirects-file-error" @enderror>
            @error('file')
                <p id="redirects-file-error" class="text-sm text-fg-danger">{{ $message }}</p>
            @enderror
            <p class="text-xs text-fg-tertiary">{{ __('One redirect per line: from,to,status (301, 302 or 410). A header row is optional.') }}</p>
            <div><x-ui.button variant="secondary" size="sm">{{ __('Import') }}</x-ui.button></div>
        </form>
    </div>

    <x-ui.card padding="p-0" class="overflow-x-auto">
        @if ($redirects->isEmpty())
            <x-ui.empty-state :title="__('No redirects yet')" :description="__('Redirects are also created automatically when a published page’s slug changes.')" />
        @else
            <table class="w-full text-sm">
                <thead class="bg-subtle text-xs text-fg-tertiary">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('From') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('To') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Type') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Hits') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Status') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold"><span class="sr-only">{{ __('Actions') }}</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($redirects as $redirect)
                        <tr class="border-t border-line-subtle">
                            <td class="px-4 py-3"><a href="{{ route('admin.redirects.edit', $redirect) }}" class="break-all font-mono text-xs font-semibold text-fg hover:text-fg-brand" dir="ltr">{{ $redirect->from_path }}</a>
                                @if ($redirect->is_automatic)
                                    <x-ui.badge :dot="false" class="ms-1">{{ __('Automatic') }}</x-ui.badge>
                                @endif
                            </td>
                            <td class="px-4 py-3 break-all font-mono text-xs text-fg-secondary" dir="ltr">{{ $redirect->to_path ?? '—' }}</td>
                            <td class="px-4 py-3"><x-ui.badge :tone="$redirect->status_code === 410 ? 'warning' : 'info'" :dot="false">{{ $redirect->status_code }}</x-ui.badge></td>
                            <td class="px-4 py-3 text-fg-secondary">{{ number_format($redirect->hits) }}
                                @if ($redirect->last_hit_at)
                                    <span class="block text-xs text-fg-tertiary">{{ $redirect->last_hit_at->diffForHumans() }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3"><x-ui.badge :tone="$redirect->is_active ? 'success' : 'neutral'">{{ $redirect->is_active ? __('Active') : __('Inactive') }}</x-ui.badge></td>
                            <td class="px-4 py-3">
                                <form method="POST" action="{{ route('admin.redirects.destroy', $redirect) }}" data-confirm="{{ __('Delete this redirect? Visitors to the old URL will see a 404.') }}">
                                    @csrf
                                    @method('DELETE')
                                    <x-ui.button variant="ghost" size="sm">{{ __('Delete') }}</x-ui.button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-ui.card>

    {{ $redirects->links() }}
</x-layouts.admin>
