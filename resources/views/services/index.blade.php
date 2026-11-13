<x-layouts.public
    :title="__('Software services for every stage of growth')"
    :description="__('Strategy, design, engineering and support — combine what you need, scale when you’re ready.')"
    :alternates="\App\Models\Language::active()->keys()->mapWithKeys(fn ($code) => [$code => route('services.index', ['locale' => $code])])->all()">
    <x-site.page-hero :eyebrow="__('Services')" :title="__('Software services for every stage of growth')"
                      :lead="__('Strategy, design, engineering and support — combine what you need, scale when you’re ready.')"
                      :crumbs="[__('Services') => null]" />

    @if ($core->isEmpty() && $specialist->isEmpty())
        <x-site.section>
            <x-ui.empty-state :title="__('Our services are being updated')" :description="__('Please check back soon or contact us to discuss your project.')" />
        </x-site.section>
    @endif

    @if ($core->isNotEmpty())
        <x-site.section aria-labelledby="core-services">
            <x-site.section-heading id="core-services" align="start" :eyebrow="__('Core services')" :heading="__('What we build')" />
            <ul class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($core as $service)
                    <x-site.service-card :service="$service" />
                @endforeach
            </ul>
        </x-site.section>
    @endif

    @if ($specialist->isNotEmpty())
        <x-site.section tone="canvas" aria-labelledby="specialist-services">
            <x-site.section-heading id="specialist-services" align="start" :eyebrow="__('Also available')" :heading="__('Specialist services')" />
            <ul class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($specialist as $service)
                    @php($translation = $service->exactTranslation(app()->getLocale()))
                    <x-site.tile-card :icon="$service->icon ?? 'code'" :title="$translation->title" :text="$translation->summary"
                                      :href="route('services.show', ['slug' => $translation->slug])" />
                @endforeach
            </ul>
        </x-site.section>
    @endif

    <x-site.cta-band :tone="$specialist->isNotEmpty() ? 'surface' : 'canvas'" />
</x-layouts.public>
