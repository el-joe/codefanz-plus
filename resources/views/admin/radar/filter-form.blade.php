@php
    $rules = (array) $filter->rules;
    $list = fn (string $key): string => implode("\n", (array) ($rules[$key] ?? []));
    $chosenPlatforms = old('platforms', $rules['platforms'] ?? []);
    $chosenLanguages = old('languages', $rules['languages'] ?? []);
@endphp

<x-layouts.admin :title="$filter->exists ? $filter->name : __('New filter profile')" :crumb="__('Freelance Radar')">
    @include('admin.radar._nav')

    @if ($errors->any())
        <x-ui.alert tone="danger" :title="__('Please fix the highlighted fields')">{{ __('Some fields need attention.') }}</x-ui.alert>
    @endif

    <form method="POST" action="{{ $filter->exists ? route('admin.radar.filters.update', $filter) : route('admin.radar.filters.store') }}" class="flex max-w-3xl flex-col gap-6">
        @csrf
        @if ($filter->exists)
            @method('PUT')
        @endif

        <x-ui.card :title="__('Profile')">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input name="name" :label="__('Name')" :value="$filter->name" required maxlength="80" dir="auto" placeholder="Laravel & SaaS" />
                <x-ui.input name="sort_order" type="number" min="0" max="1000" :label="__('Tab order')" :value="$filter->sort_order ?? 0" />
                <label class="flex items-center gap-2 text-sm text-fg"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $filter->is_active ?? true)) class="size-4 accent-brand"> {{ __('Show as a tab') }}</label>
                <label class="flex items-center gap-2 text-sm text-fg"><input type="hidden" name="alerts_enabled" value="0"><input type="checkbox" name="alerts_enabled" value="1" @checked(old('alerts_enabled', $filter->alerts_enabled ?? true)) class="size-4 accent-brand"> {{ __('Send Telegram alerts for matches') }}</label>
            </div>
        </x-ui.card>

        <x-ui.card :title="__('Rules')">
            <p class="mb-4 text-sm text-fg-secondary">{{ __('Empty rules match everything. Lists take one entry per line or comma separated. Projects outside a profile are never deleted — they stay under “All projects”.') }}</p>
            <div class="grid gap-4 sm:grid-cols-2">
                @include('admin._textarea', ['name' => 'include_keywords', 'id' => 'include_keywords', 'label' => __('Include keywords (any)'), 'value' => $list('include_keywords'), 'rows' => 3])
                @include('admin._textarea', ['name' => 'exclude_keywords', 'id' => 'exclude_keywords', 'label' => __('Exclude keywords'), 'value' => $list('exclude_keywords'), 'rows' => 3])
                @include('admin._textarea', ['name' => 'skills', 'id' => 'skills', 'label' => __('Skills (any)'), 'value' => $list('skills'), 'rows' => 3])
                @include('admin._textarea', ['name' => 'countries', 'id' => 'countries', 'label' => __('Client countries (ISO codes)'), 'value' => $list('countries'), 'rows' => 3, 'mono' => true])
                <x-ui.input name="min_budget" type="number" min="0" :label="__('Minimum budget (USD)')" :value="$rules['min_budget'] ?? null" :helper="__('Listings without a budget still match.')" />
                <x-ui.input name="min_score" type="number" min="0" max="100" :label="__('Minimum rule score')" :value="$rules['min_score'] ?? null" />
                <x-ui.select name="budget_type" :label="__('Budget type')" :options="['fixed' => __('Fixed price'), 'hourly' => __('Hourly')]" :value="$rules['budget_type'] ?? null" :placeholder="__('Any')" />
            </div>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <fieldset>
                    <legend class="mb-2 text-sm font-semibold text-fg">{{ __('Platforms') }}</legend>
                    <div class="flex flex-wrap gap-3">
                        @foreach ($platforms as $key => $name)
                            <label class="flex items-center gap-2 text-sm text-fg"><input type="checkbox" name="platforms[]" value="{{ $key }}" @checked(in_array($key, $chosenPlatforms, true)) class="size-4 accent-brand"> {{ $name }}</label>
                        @endforeach
                    </div>
                </fieldset>
                <fieldset>
                    <legend class="mb-2 text-sm font-semibold text-fg">{{ __('Languages') }}</legend>
                    <div class="flex flex-wrap gap-3">
                        @foreach ($languages as $code => $name)
                            <label class="flex items-center gap-2 text-sm text-fg"><input type="checkbox" name="languages[]" value="{{ $code }}" @checked(in_array($code, $chosenLanguages, true)) class="size-4 accent-brand"> {{ $name }}</label>
                        @endforeach
                    </div>
                </fieldset>
            </div>
        </x-ui.card>

        <div class="flex flex-wrap gap-3">
            <x-ui.button>{{ __('Save filter profile') }}</x-ui.button>
            <x-ui.button variant="ghost" :href="route('admin.radar.settings')">{{ __('Cancel') }}</x-ui.button>
        </div>
    </form>

    @if ($filter->exists)
        <div class="max-w-3xl">
            @include('admin._trash-card', ['action' => route('admin.radar.filters.destroy', $filter), 'confirm' => __('Delete the filter profile :name? Projects are not affected.', ['name' => $filter->name]), 'label' => __('Delete filter profile')])
        </div>
    @endif
</x-layouts.admin>
