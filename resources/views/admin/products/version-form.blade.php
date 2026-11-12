@php($productName = $product->translation()?->name ?? '#'.$product->id)

<x-layouts.admin :title="$version->exists ? __('Edit version') : __('New version')" :crumb="$productName">
    @if ($errors->any())
        <x-ui.alert tone="danger" :title="__('Please fix the highlighted fields')">{{ __('Some fields need attention.') }}</x-ui.alert>
    @endif

    <div class="grid gap-6 xl:grid-cols-[1fr_340px]">
        <form id="version-form" method="POST" action="{{ $version->exists ? route('admin.products.versions.update', [$product, $version]) : route('admin.products.versions.store', $product) }}" class="flex min-w-0 flex-col gap-6">
            @csrf
            @if ($version->exists)
                @method('PUT')
            @endif

            @foreach (\App\Models\Language::active() as $code => $language)
                <x-ui.card :title="$language['native_name']" lang="{{ $code }}" dir="{{ $language['direction'] }}">
                    <div class="flex flex-col gap-1.5">
                        <label for="changelog-{{ $code }}" class="text-sm font-semibold text-fg">{{ __('Changelog (Markdown)') }}</label>
                        <textarea id="changelog-{{ $code }}" name="translations[{{ $code }}][changelog]" rows="8" class="rounded-md border border-line bg-surface px-3 py-2 font-mono text-sm text-fg">{{ old("translations.{$code}.changelog", $version->exists ? $version->exactTranslation($code)?->changelog : null) }}</textarea>
                        <p class="text-xs text-fg-tertiary">{{ __('One change per line, e.g. “- Offline POS sync”. Raw HTML is removed for security.') }}</p>
                        @error("translations.{$code}.changelog")<p class="text-sm text-fg-danger">{{ $message }}</p>@enderror
                    </div>
                </x-ui.card>
            @endforeach
        </form>

        <div class="flex flex-col gap-6">
            <x-ui.card :title="__('Release')">
                <div class="flex flex-col gap-4">
                    <x-ui.input name="version" form="version-form" :label="__('Version number')" :value="$version->version" maxlength="30" dir="ltr" placeholder="2.3" />
                    <x-ui.input name="released_at" form="version-form" type="date" :label="__('Release date')" :value="$version->released_at?->toDateString()" />
                    <x-ui.button form="version-form">{{ $version->exists ? __('Save changes') : __('Add version') }}</x-ui.button>
                    <a href="{{ route('admin.products.edit', $product) }}" class="text-center text-sm font-semibold text-fg-brand hover:underline">{{ __('Back to :product', ['product' => $productName]) }}</a>
                </div>
            </x-ui.card>

            @if ($version->exists)
                <x-ui.card>
                    <form method="POST" action="{{ route('admin.products.versions.destroy', [$product, $version]) }}" data-confirm="{{ __('Delete this version and its changelog? This cannot be undone.') }}" data-confirm-label="{{ __('Delete') }}">
                        @csrf
                        @method('DELETE')
                        <x-ui.button variant="ghost" class="w-full text-fg-danger">{{ __('Delete version') }}</x-ui.button>
                    </form>
                </x-ui.card>
            @endif
        </div>
    </div>
</x-layouts.admin>
