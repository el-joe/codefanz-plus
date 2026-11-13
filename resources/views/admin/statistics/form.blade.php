<x-layouts.admin :title="$statistic->exists ? __('Edit statistic') : __('New statistic')" :crumb="__('Statistics')">
    @if ($errors->any())
        <x-ui.alert tone="danger" :title="__('Please fix the highlighted fields')">{{ $errors->first('status') ?: __('Some fields need attention.') }}</x-ui.alert>
    @endif

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
        <form id="statistic-form" method="POST" action="{{ $statistic->exists ? route('admin.statistics.update', $statistic) : route('admin.statistics.store') }}" class="flex min-w-0 flex-col gap-6">
            @csrf
            @if ($statistic->exists)
                @method('PUT')
            @endif

            <x-ui.card :title="__('Figure')">
                <x-ui.input name="value" type="number" step="0.01" min="0" :label="__('Value')" :value="$statistic->exists ? rtrim(rtrim((string) $statistic->value, '0'), '.') : null" dir="ltr" required
                            :helper="__('Numbers only, e.g. 120 or 98.5. Add “+” or “%” as a suffix per language.')" />
            </x-ui.card>

            @foreach (\App\Models\Language::active() as $code => $language)
                @php($translation = $statistic->exists ? $statistic->exactTranslation($code) : null)
                <x-ui.card :title="$language['native_name']" lang="{{ $code }}" dir="{{ $language['direction'] }}">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-[minmax(0,1fr)_160px]">
                        <x-ui.input :name="'translations['.$code.'][label]'" :id="'label-'.$code" :label="__('Label')" :value="$translation?->label" maxlength="80" :helper="__('E.g. Projects delivered.')" />
                        <x-ui.input :name="'translations['.$code.'][suffix]'" :id="'suffix-'.$code" :label="__('Suffix')" :value="$translation?->suffix" maxlength="10" dir="ltr" />
                    </div>
                </x-ui.card>
            @endforeach
        </form>

        <div class="flex flex-col gap-6">
            @include('admin._publish-card', ['model' => $statistic, 'form' => 'statistic-form', 'publishUrl' => $statistic->exists ? route('admin.statistics.publish', $statistic) : null, 'liveUrl' => route('home', ['locale' => app()->getLocale()])])

            <x-ui.card :title="__('Details')">
                <x-ui.input name="sort_order" form="statistic-form" type="number" min="0" max="65535" :label="__('Sort order')" :value="$statistic->sort_order ?? 0" :helper="__('Lower numbers appear first.')" />
            </x-ui.card>

            @if ($statistic->exists)
                @include('admin._trash-card', ['action' => route('admin.statistics.destroy', $statistic), 'confirm' => __('Delete this statistic? This cannot be undone.'), 'label' => __('Delete statistic')])
            @endif
        </div>
    </div>
</x-layouts.admin>
