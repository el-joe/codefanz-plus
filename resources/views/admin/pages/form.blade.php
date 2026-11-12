<x-layouts.admin :title="$page->exists ? __('Edit page') : __('New page')" :crumb="__('Pages')">
    @if ($errors->any())
        <x-ui.alert tone="danger" :title="__('Please fix the highlighted fields')">{{ $errors->first('lock_version') ?: $errors->first('status') ?: __('Some fields need attention.') }}</x-ui.alert>
    @endif

    @if ($pendingChanges->isNotEmpty())
        <x-ui.alert tone="warning" :title="__('Pending AI/MCP proposals for this page')">
            {{ __('Saving here creates a new version, so these proposals will become stale and cannot be applied:') }}
            @foreach ($pendingChanges as $change)
                <a href="{{ route('admin.approvals.show', $change) }}" class="font-mono font-semibold underline">{{ $change->reference }}</a>
            @endforeach
        </x-ui.alert>
    @endif

    <div class="grid gap-6 xl:grid-cols-[1fr_340px]">
        <form id="page-form" method="POST" action="{{ $page->exists ? route('admin.pages.update', $page) : route('admin.pages.store') }}" class="flex flex-col gap-6">
            @csrf
            @if ($page->exists)
                @method('PUT')
                <input type="hidden" name="lock_version" value="{{ $page->lock_version }}">
            @endif

            @foreach (\App\Models\Language::active() as $code => $language)
                @php($translation = $page->exists ? $page->translation($code) : null)
                <x-ui.card :title="$language['native_name']" lang="{{ $code }}" dir="{{ $language['direction'] }}">
                    <div class="flex flex-col gap-4">
                        <x-ui.input :name="'translations['.$code.'][title]'" :id="'title-'.$code" :label="__('Title')" :value="$translation?->title" />
                        <x-ui.input :name="'translations['.$code.'][slug]'" :id="'slug-'.$code" :label="__('URL slug')" :value="$translation?->slug" :helper="url($code).'/…'" />
                        <div class="flex flex-col gap-1.5">
                            <label for="body-{{ $code }}" class="text-sm font-semibold text-fg">{{ __('Content (Markdown)') }}</label>
                            <textarea id="body-{{ $code }}" name="translations[{{ $code }}][body]" rows="12" class="rounded-md border border-line bg-surface px-3 py-2 font-mono text-sm text-fg">{{ old("translations.{$code}.body", $translation?->body) }}</textarea>
                            <p class="text-xs text-fg-tertiary">{{ __('Headings, lists and links are supported. Raw HTML is removed for security.') }}</p>
                        </div>
                        <x-ui.input :name="'translations['.$code.'][seo_title]'" :id="'seo-title-'.$code" :label="__('SEO title')" :value="$translation?->seo_title" maxlength="70" :helper="__('Up to 70 characters.')" />
                        <x-ui.input :name="'translations['.$code.'][seo_description]'" :id="'seo-description-'.$code" :label="__('Meta description')" :value="$translation?->seo_description" maxlength="170" :helper="__('Up to 170 characters.')" />
                    </div>
                </x-ui.card>
            @endforeach
        </form>

        <div class="flex flex-col gap-6">
            <x-ui.card :title="__('Publishing')">
                <div class="flex flex-col gap-4">
                    <div class="flex flex-col gap-1.5">
                        <label for="template" class="text-sm font-semibold text-fg">{{ __('Template') }}</label>
                        <select id="template" name="template" form="page-form" class="rounded-md border border-line bg-surface px-3 py-2 text-sm text-fg">
                            @foreach (['default' => __('Standard page'), 'legal' => __('Legal page')] as $value => $label)
                                <option value="{{ $value }}" @selected(old('template', $page->template) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if ($page->exists)
                        <p class="text-sm text-fg-secondary">{{ __('Status') }}: <x-ui.badge :tone="$page->status === \App\Enums\PageStatus::Published ? 'success' : 'neutral'">{{ __(ucfirst($page->status->value)) }}</x-ui.badge> · {{ __('Version :version', ['version' => $page->lock_version]) }}</p>
                    @endif
                    <x-ui.button form="page-form">{{ $page->exists ? __('Save changes') : __('Create draft') }}</x-ui.button>
                    @if ($page->exists)
                        <form method="POST" action="{{ route('admin.pages.publish', $page) }}">
                            @csrf
                            <x-ui.button variant="secondary" class="w-full">{{ $page->status === \App\Enums\PageStatus::Published ? __('Unpublish') : __('Publish') }}</x-ui.button>
                        </form>
                        @if ($page->status === \App\Enums\PageStatus::Published && ($live = $page->translation()))
                            <a href="{{ route('pages.show', ['locale' => app()->getLocale(), 'slug' => $live->slug]) }}" class="text-sm font-semibold text-fg-brand hover:underline" target="_blank" rel="noopener">{{ __('View live page') }}</a>
                        @endif
                    @endif
                </div>
            </x-ui.card>

            @if ($page->exists)
                <x-ui.card>
                    <form method="POST" action="{{ route('admin.pages.destroy', $page) }}" data-confirm="{{ __('Move this page to trash? It will no longer be visible on the site.') }}" data-confirm-label="{{ __('Move to trash') }}">
                        @csrf
                        @method('DELETE')
                        <x-ui.button variant="ghost" class="w-full text-fg-danger">{{ __('Move to trash') }}</x-ui.button>
                    </form>
                </x-ui.card>
            @endif
        </div>
    </div>
</x-layouts.admin>
