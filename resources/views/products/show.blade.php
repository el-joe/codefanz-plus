@php
    $locale = app()->getLocale();
    $cover = $product->coverImage();
    $latest = $product->latestVersion();
    $features = $translation->filledFeatures();
    $models = $product->pricing_models ?? collect();
    $prose = 'flex flex-col gap-4 text-lg leading-relaxed text-fg-secondary [&_a]:text-fg-brand [&_a]:underline [&_h2]:mt-4 [&_h2]:text-2xl [&_h2]:font-bold [&_h2]:text-fg [&_h3]:text-xl [&_h3]:font-semibold [&_h3]:text-fg [&_ol]:list-decimal [&_ol]:ps-6 [&_ul]:list-disc [&_ul]:ps-6';
@endphp

<x-layouts.public
    :title="$translation->seo_title ?: $translation->name"
    :description="$translation->seo_description ?: ($translation->tagline ?: $translation->name)"
    :image="$cover"
    :alternates="$product->translations->mapWithKeys(fn ($alt) => [$alt->locale => route('products.show', ['locale' => $alt->locale, 'slug' => $alt->slug])])->all()">
    <x-site.page-hero :title="$translation->name" :lead="$translation->tagline"
                      :crumbs="[__('Products') => route('products.index'), $translation->name => null]">
        <x-slot:before>
            <div class="flex flex-wrap items-center gap-3">
                <span class="flex size-12 items-center justify-center rounded-lg bg-brand text-fg-on-brand"><x-ui.icon :name="$product->icon ?? 'layers'" :size="24" /></span>
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
                <x-ui.button :href="'#request-demo'">{{ __('Request a demo') }}</x-ui.button>
            @endif
            <x-ui.button variant="secondary" :href="route('contact', ['topic' => 'quote', 'ref' => $translation->name])">{{ __('Contact sales') }}</x-ui.button>
        </div>
    </x-site.page-hero>

    @if ($cover || $product->screenshots->isNotEmpty())
        <section class="bg-subtle" aria-label="{{ __('Screenshots') }}">
            <div class="mx-auto flex max-w-7xl flex-col gap-4 px-5 pb-12 lg:px-20">
                @if ($cover)
                    <x-media.picture :media="$cover" sizes="(min-width: 1280px) 1120px, 100vw" :eager="true" class="w-full rounded-2xl border border-line" />
                @endif
                @if ($product->screenshots->isNotEmpty())
                    <ul class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                        @foreach ($product->screenshots as $image)
                            <li><x-media.picture :media="$image" sizes="(min-width: 1024px) 270px, 50vw" class="aspect-[16/10] w-full rounded-xl border border-line object-cover" /></li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </section>
    @endif

    <div class="mx-auto flex max-w-7xl flex-col gap-16 px-5 py-14 lg:px-20 lg:py-20">
        @if (trim($description) !== '')
            <div class="{{ $prose }} max-w-3xl">{!! $description !!}</div>
        @endif

        @if ($features !== [])
            <section aria-labelledby="features" class="flex flex-col gap-6">
                <div class="flex flex-col gap-1">
                    <p class="text-xs font-bold uppercase tracking-wider text-fg-brand">{{ __('Features') }}</p>
                    <h2 id="features" class="text-3xl font-bold text-fg">{{ __('Everything you need to run operations') }}</h2>
                </div>
                <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($features as $feature)
                        <li class="flex flex-col gap-1.5 rounded-lg border border-line bg-surface p-5">
                            <span class="font-semibold text-fg">{{ $feature['title'] }}</span>
                            @if ($feature['description'] ?? null)
                                <span class="text-sm text-fg-secondary">{{ $feature['description'] }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($models->isNotEmpty())
            <section aria-labelledby="licensing" class="flex flex-col items-center gap-8 rounded-2xl bg-subtle px-5 py-12 text-center lg:px-10">
                <div class="flex max-w-2xl flex-col gap-2">
                    <p class="text-xs font-bold uppercase tracking-wider text-fg-brand">{{ __('Licensing') }}</p>
                    <h2 id="licensing" class="text-3xl font-bold text-fg">{{ __('Choose how you license :product', ['product' => $translation->name]) }}</h2>
                    <p class="text-fg-secondary">{{ __('Final pricing depends on users, branches and customization. All quotes are preliminary until scope is confirmed.') }}</p>
                </div>
                <ul class="grid w-full gap-5 text-start md:grid-cols-3" data-licensing-models>
                    @foreach ($models as $model)
                        <li class="flex flex-col gap-4 rounded-xl border bg-surface p-6 {{ $loop->first ? 'border-line-brand shadow-md' : 'border-line' }}">
                            @if ($loop->first && $models->count() > 1)
                                <x-ui.badge tone="brand" class="w-fit">{{ __('Most chosen') }}</x-ui.badge>
                            @endif
                            <h3 class="text-lg font-semibold text-fg">{{ $model->label() }}</h3>
                            <p class="flex-1 text-sm text-fg-secondary">{{ $model->description() }}</p>
                            <x-ui.button :variant="$loop->first ? 'primary' : 'secondary'" :href="route('contact', ['topic' => 'quote', 'ref' => $translation->name.' — '.$model->label()])">{{ __('Get a quote') }}</x-ui.button>
                        </li>
                    @endforeach
                </ul>
                @if ($translation->licensing_notes)
                    <p class="max-w-2xl whitespace-pre-line text-sm text-fg-tertiary">{{ $translation->licensing_notes }}</p>
                @endif
            </section>
        @endif

        <div class="grid gap-10 lg:grid-cols-[1fr_340px]">
            <section aria-labelledby="changelog" class="flex flex-col gap-6">
                <h2 id="changelog" class="text-3xl font-bold text-fg">{{ __('Versions & changelog') }}</h2>
                @if ($changelogs === [])
                    <p class="text-fg-secondary">{{ __('The first public release notes will appear here.') }}</p>
                @else
                    <ol class="flex flex-col gap-6">
                        @foreach ($product->versions as $version)
                            @continue(! isset($changelogs[$version->id]))
                            <li class="flex flex-col gap-2 border-s-2 border-line ps-4">
                                <p class="flex flex-wrap items-center gap-2">
                                    <x-ui.badge :tone="$loop->first ? 'brand' : 'neutral'" :dot="false"><span dir="ltr">v{{ $version->version }}</span></x-ui.badge>
                                    @if ($version->released_at)
                                        <time datetime="{{ $version->released_at->toDateString() }}" class="text-sm text-fg-tertiary">{{ $version->released_at->locale($locale)->isoFormat('MMM YYYY') }}</time>
                                    @endif
                                </p>
                                <div class="text-sm text-fg-secondary [&_a]:text-fg-brand [&_a]:underline [&_ul]:list-disc [&_ul]:ps-5 [&_ol]:list-decimal [&_ol]:ps-5">{!! $changelogs[$version->id] !!}</div>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>

            <aside class="flex flex-col gap-4">
                @if ($product->docs_url)
                    <div class="flex flex-col gap-2 rounded-xl border border-line bg-surface p-5">
                        <h3 class="font-semibold text-fg">{{ __('Documentation') }}</h3>
                        <p class="text-sm text-fg-secondary">{{ __('User guides, admin manual and integration notes.') }}</p>
                        <a href="{{ $product->docs_url }}" target="_blank" rel="noopener noreferrer" class="text-sm font-semibold text-fg-brand hover:underline">{{ __('Open docs') }} <span class="sr-only">({{ __('opens in a new tab') }})</span></a>
                    </div>
                @endif
                @if ($product->technologies->isNotEmpty())
                    <div class="flex flex-col gap-2 rounded-xl border border-line bg-surface p-5">
                        <h3 class="font-semibold text-fg">{{ __('Built with') }}</h3>
                        <ul class="flex flex-wrap gap-2">
                            @foreach ($product->technologies as $technology)
                                <x-site.tech-chip :technology="$technology" />
                            @endforeach
                        </ul>
                    </div>
                @endif
            </aside>
        </div>

        @if ($product->demo_enabled)
            <section id="request-demo" aria-labelledby="request-demo-title" class="mx-auto flex w-full max-w-2xl scroll-mt-6 flex-col gap-4 rounded-2xl border border-line bg-surface p-8 shadow-md">
                <h2 id="request-demo-title" class="text-2xl font-bold text-fg">{{ __('Request a :product demo', ['product' => $translation->name]) }}</h2>
                <p class="text-fg-secondary">{{ __('A product specialist will walk you through the platform using your workflows. Tell us your company, team size and number of branches.') }}</p>
                <x-ui.button class="w-fit" :href="route('contact', ['topic' => 'demo', 'ref' => $translation->name])">{{ __('Request demo') }}</x-ui.button>
            </section>
        @endif
    </div>

    <x-site.cta-band :title="__('Not sure which product fits?')" :lead="__('Talk to our team — we’ll recommend a product or a custom build.')" :action="__('Contact sales')" />
</x-layouts.public>
