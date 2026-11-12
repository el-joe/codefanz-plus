<x-layouts.admin :title="$redirect->exists ? __('Edit redirect') : __('New redirect')" :crumb="__('Redirects')">
    @include('admin.seo._tabs')

    @if ($errors->any())
        <x-ui.alert tone="danger" :title="__('Please fix the highlighted fields')">{{ __('Some fields need attention.') }}</x-ui.alert>
    @endif

    <form method="POST" action="{{ $redirect->exists ? route('admin.redirects.update', $redirect) : route('admin.redirects.store') }}" class="flex max-w-3xl flex-col gap-6">
        @csrf
        @if ($redirect->exists)
            @method('PUT')
        @endif

        <x-ui.card>
            <div class="flex flex-col gap-5">
                <x-ui.input name="from_path" :label="__('From (path on this site)')" :value="$redirect->from_path" required maxlength="500" dir="ltr" placeholder="/en/old-page" :helper="__('Query strings are ignored. Checked only when the path would otherwise show “not found”.')" />
                <x-ui.select name="status_code" :label="__('Type')" :value="$redirect->status_code" :options="[301 => __('301 · Moved permanently'), 302 => __('302 · Temporary'), 410 => __('410 · Gone (no target)')]" />
                <x-ui.input name="to_path" :label="__('To (path on this site or https:// URL)')" :value="$redirect->to_path" maxlength="1000" dir="ltr" placeholder="/en/new-page" :helper="__('Leave blank for 410. Redirect chains and loops are rejected.')" />
                <x-ui.input name="note" :label="__('Note (optional)')" :value="$redirect->note" maxlength="255" dir="auto" />
                <label class="flex items-center gap-2 text-sm text-fg">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $redirect->exists ? $redirect->is_active : true)) class="size-4 accent-brand">
                    {{ __('Active') }}
                </label>
            </div>
        </x-ui.card>

        <div class="flex gap-3">
            <x-ui.button>{{ __('Save redirect') }}</x-ui.button>
            <x-ui.button :href="route('admin.redirects.index')" variant="secondary">{{ __('Cancel') }}</x-ui.button>
        </div>
    </form>
</x-layouts.admin>
