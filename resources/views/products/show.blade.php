@php
    $locale = app()->getLocale();
    $cover = $product->coverImage();
    $latest = $product->latestVersion();
    $features = $translation->filledFeatures();
    $models = $product->pricing_models ?? collect();
    $prose = 'flex flex-col gap-3 type-body-lg text-fg-secondary [&_a]:text-fg-brand [&_a]:underline [&_h2]:mt-2 [&_h2]:type-h4 [&_h2]:text-fg [&_h3]:type-h5 [&_h3]:text-fg [&_ol]:list-decimal [&_ol]:ps-6 [&_ul]:list-disc [&_ul]:ps-6';
    $featureIcons = ['layers', 'cart', 'credit-card', 'trending-up', 'users', 'plug', 'shield', 'database', 'cloud'];
    $resourceCard = 'flex flex-col items-start gap-3 rounded-[18px] border border-line bg-surface p-6';
    $resourceTile = 'flex size-11 items-center justify-center rounded-lg bg-accent-subtle text-icon-primary';
    $mainImage = $cover ?? $product->screenshots->first();
    $thumbnails = $cover ? $product->screenshots : $product->screenshots->slice(1);
@endphp

<x-layouts.public
    :title="$translation->seo_title ?: $translation->name"
    :description="$translation->seo_description ?: ($translation->tagline ?: $translation->name)"
    :image="$cover"
    :seoable="$product"
    :alternates="$product->translations->mapWithKeys(fn ($alt) => [$alt->locale => route('products.show', ['locale' => $alt->locale, 'slug' => $alt->slug])])->all()">
    <x-slot:head>
        @php($structuredData = app(\App\Services\Seo\StructuredData::class))
        {{ $structuredData->script($structuredData->product($translation->name, $translation->tagline, route('products.show', ['slug' => $translation->slug]), $cover?->largestVariant('webp') ? url($cover->largestVariant('webp')->url()) : null)) }}
    </x-slot:head>
    <x-site.page-hero :title="$translation->name" :lead="$translation->tagline"
                      :crumbs="[__('Products') => route('products.index'), $translation->name => null]">
        <x-slot:before>
            <div class="flex flex-wrap items-center gap-3">
                <span class="flex size-14 items-center justify-center rounded-lg bg-brand text-fg-on-brand" aria-hidden="true"><x-ui.icon :name="$product->icon ?? 'layers'" :size="28" /></span>
                <x-ui.badge :tone="$product->availability->tone()">{{ $product->availability->label() }}</x-ui.badge>
                @if ($latest)
                    <x-ui.badge tone="neutral" :dot="false"><span dir="ltr">v{{ $latest->version }}</span></x-ui.badge>
                @endif
                @if ($models->isNotEmpty())
                    <x-ui.badge tone="info" :dot="false">{{ $models->first()->label() }}</x-ui.badge>
                @endif
            </div>
        </x-slot:before>
        <div class="flex flex-wrap gap-3">
            @if ($product->demo_enabled)
                <x-ui.button :href="'#request-demo'" size="lg" arrow>{{ __('Request a demo') }}</x-ui.button>
            @endif
            <x-ui.button variant="secondary" size="lg" :href="route('contact', ['topic' => 'quote', 'ref' => $translation->name])">{{ __('Contact sales') }}</x-ui.button>
        </div>
    </x-site.page-hero>

    <section class="bg-canvas pb-14 lg:pb-24" aria-label="{{ __('Screenshots') }}">
        <div class="container-page flex flex-col gap-4">
            @if ($mainImage)
                <x-media.picture :media="$mainImage" sizes="(min-width: 1440px) 1280px, 100vw" :eager="true" class="aspect-[16/7] w-full rounded-[24px] border border-line object-cover" />
            @else
                <x-site.screen-mockup tone="deep" layout="table" :window="[1024, 448]" class="aspect-[35/22] rounded-[24px] sm:aspect-[16/7]" />
            @endif
            @if ($thumbnails->isNotEmpty())
                <ul class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    @foreach ($thumbnails as $image)
                        <li><x-media.picture :media="$image" sizes="(min-width: 1024px) 308px, 50vw" class="aspect-[77/45] w-full rounded-lg border border-line object-cover" /></li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>

    @if ($features !== [] || trim($description) !== '')
        <x-site.section tone="surface" aria-labelledby="features">
            <div class="flex flex-col gap-5">
                <x-site.section-heading id="features" align="start" :eyebrow="__('Features')" :heading="__('Everything you need to run operations')" />
                @if (trim($description) !== '')
                    <div class="{{ $prose }} max-w-3xl">{!! $description !!}</div>
                @endif
            </div>
            @if ($features !== [])
                <ul class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 lg:gap-6">
                    @foreach ($features as $feature)
                        <li class="flex flex-col items-start gap-3 rounded-[18px] border border-line bg-surface p-6">
                            <span class="flex size-11 items-center justify-center rounded-lg bg-brand-subtle text-icon-brand" aria-hidden="true"><x-ui.icon :name="$featureIcons[$loop->index % count($featureIcons)]" :size="22" /></span>
                            <h3 class="type-h5 text-fg">{{ $feature['title'] }}</h3>
                            @if ($feature['description'] ?? null)
                                <p class="type-body-sm text-fg-secondary">{{ $feature['description'] }}</p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-site.section>
    @endif

    @if ($models->isNotEmpty())
        <x-site.section tone="canvas" id="licensing-models" aria-labelledby="licensing">
            <x-site.section-heading id="licensing" :eyebrow="__('Licensing')" :heading="__('Choose how you license :product', ['product' => $translation->name])"
                                    :subheading="__('Final pricing depends on users, branches and customization. All quotes are preliminary until scope is confirmed.')" />
            <ul class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3 lg:items-start" data-licensing-models>
                @foreach ($models as $model)
                    @php($highlighted = $loop->first && $models->count() > 1)
                    <li class="flex flex-col items-start gap-4 rounded-xl px-7 py-8 {{ $highlighted ? 'border-2 border-line-brand bg-brand-subtle' : 'border border-line bg-surface' }}">
                        @if ($highlighted)
                            <x-ui.badge tone="brand">{{ __('Most chosen') }}</x-ui.badge>
                        @endif
                        <h3 class="type-h4 text-fg">{{ $model->label() }}</h3>
                        <p class="type-body-sm text-fg-secondary">{{ $model->description() }}</p>
                        <ul class="flex flex-col gap-2">
                            @foreach ($model->highlights() as $highlight)
                                <li class="flex items-center gap-2.5 type-body-md text-fg">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="shrink-0 text-icon-brand"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg>
                                    {{ $highlight }}
                                </li>
                            @endforeach
                        </ul>
                        <x-ui.button class="mt-auto w-full" :variant="$highlighted ? 'primary' : 'secondary'" :href="route('quote', ['ref' => $translation->name.' — '.$model->label()])">{{ __('Get a quote') }}</x-ui.button>
                    </li>
                @endforeach
            </ul>
            @if ($translation->licensing_notes)
                <p class="mx-auto max-w-[45rem] whitespace-pre-line text-center type-body-sm text-fg-tertiary">{{ $translation->licensing_notes }}</p>
            @endif
        </x-site.section>
    @endif

    <x-site.section tone="surface" inner="grid gap-8 lg:grid-cols-[minmax(0,1fr)_400px] lg:gap-16">
        <section aria-labelledby="changelog" class="flex min-w-0 flex-col gap-6">
            <h2 id="changelog" class="type-h3 text-fg">{{ __('Versions & changelog') }}</h2>
            @if ($changelogs === [])
                <p class="type-body-md text-fg-secondary">{{ __('The first public release notes will appear here.') }}</p>
            @else
                <ol class="flex flex-col">
                    @foreach ($product->versions as $version)
                        @continue(! isset($changelogs[$version->id]))
                        <li class="flex flex-col gap-2 border-b border-line py-5">
                            <p class="flex flex-wrap items-center gap-3">
                                <x-ui.badge :tone="$loop->first ? 'brand' : 'neutral'" :dot="false"><span dir="ltr">v{{ $version->version }}</span></x-ui.badge>
                                @if ($version->released_at)
                                    <time datetime="{{ $version->released_at->toDateString() }}" class="type-body-sm text-fg-tertiary">{{ $version->released_at->locale($locale)->isoFormat('MMM YYYY') }}</time>
                                @endif
                            </p>
                            <div class="flex flex-col gap-2 type-body-md text-fg [&_a]:text-fg-brand [&_a]:underline [&_ol]:list-decimal [&_ol]:ps-5 [&_ul]:flex [&_ul]:flex-col [&_ul]:gap-2 [&_ul>li]:relative [&_ul>li]:ps-3.5 [&_ul>li]:before:absolute [&_ul>li]:before:start-0 [&_ul>li]:before:top-2.5 [&_ul>li]:before:size-1.5 [&_ul>li]:before:rounded-full [&_ul>li]:before:bg-brand">{!! $changelogs[$version->id] !!}</div>
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>

        <aside class="flex flex-col gap-4" aria-label="{{ __('Product resources') }}">
            @if ($product->docs_url)
                <div class="{{ $resourceCard }}">
                    <span class="{{ $resourceTile }}" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h4"/></svg></span>
                    <h3 class="type-h5 text-fg">{{ __('Documentation') }}</h3>
                    <p class="type-body-sm text-fg-secondary">{{ __('User guides, admin manual and integration notes.') }}</p>
                    <a href="{{ $product->docs_url }}" target="_blank" rel="noopener noreferrer" class="-ms-3 inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 type-label-md text-fg-brand hover:bg-brand-subtle">{{ __('Open docs') }}<x-ui.arrow :size="16" /><span class="sr-only">({{ __('opens in a new tab') }})</span></a>
                </div>
            @endif
            @if ($models->isNotEmpty())
                <div class="{{ $resourceCard }}">
                    <span class="{{ $resourceTile }}" aria-hidden="true"><x-ui.icon name="shield" :size="22" /></span>
                    <h3 class="type-h5 text-fg">{{ __('Licensing') }}</h3>
                    <p class="type-body-sm text-fg-secondary">{{ __('Licensing models, support and how quotes are prepared.') }}</p>
                    <a href="#licensing-models" class="-ms-3 inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 type-label-md text-fg-brand hover:bg-brand-subtle">{{ __('View licensing') }}<x-ui.arrow :size="16" /></a>
                </div>
            @endif
            @if ($product->technologies->isNotEmpty())
                <div class="{{ $resourceCard }}">
                    <h3 class="type-h5 text-fg">{{ __('Built with') }}</h3>
                    <ul class="flex flex-wrap gap-2">
                        @foreach ($product->technologies as $technology)
                            <x-site.tech-chip :technology="$technology" />
                        @endforeach
                    </ul>
                </div>
            @endif
        </aside>
    </x-site.section>

    @if ($product->demo_enabled)
        <x-site.section tone="canvas" inner="">
            <section id="request-demo" aria-labelledby="request-demo-title" class="mx-auto flex w-full max-w-[45rem] scroll-mt-24 flex-col items-start gap-5 rounded-[24px] border border-line bg-surface p-6 shadow-lg sm:p-10">
                <h2 id="request-demo-title" class="type-h3 text-fg">{{ __('Request a :product demo', ['product' => $translation->name]) }}</h2>
                <p class="type-body-md text-fg-secondary">{{ __('A product specialist will walk you through the platform using your workflows. Tell us your company, team size and number of branches.') }}</p>
                <x-ui.button size="lg" arrow :href="route('contact', ['topic' => 'demo', 'ref' => $translation->name])">{{ __('Request demo') }}</x-ui.button>
            </section>
        </x-site.section>
    @else
        <x-site.cta-band :title="__('Not sure which product fits?')" :lead="__('Talk to our team — we’ll recommend a product or a custom build.')" :action="__('Contact sales')" />
    @endif
</x-layouts.public>
