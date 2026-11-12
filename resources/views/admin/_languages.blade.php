<span class="flex gap-1.5">
    @foreach (\App\Models\Language::active() as $code => $language)
        <x-ui.badge :tone="$model->translations->contains('locale', $code) ? 'success' : 'neutral'" :dot="false">{{ strtoupper($code) }}</x-ui.badge>
    @endforeach
</span>
