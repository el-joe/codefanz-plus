<x-layouts.public
    :title="__('Turning Ideas Into Powerful Digital Products')"
    :description="__('CodeFanz designs, builds and scales web platforms, mobile apps, SaaS products and AI-powered automation.')"
    :alternates="\App\Models\Language::active()->keys()->mapWithKeys(fn ($code) => [$code => route('home', ['locale' => $code])])->all()">
    <section class="bg-[radial-gradient(ellipse_at_top_right,rgb(110_86_255/0.18),transparent_60%)]">
        <div class="mx-auto flex max-w-7xl flex-col gap-7 px-5 py-20 lg:px-20 lg:py-28">
            <x-ui.badge tone="accent" class="w-fit">{{ __('Software development & digital solutions') }}</x-ui.badge>
            <h1 class="max-w-4xl text-4xl font-extrabold leading-tight tracking-tight text-fg lg:text-7xl">
                {{ __('Turning Ideas Into') }} <span class="text-fg-brand">{{ __('Powerful Digital Products') }}</span>
            </h1>
            <p class="max-w-2xl text-lg text-fg-secondary">{{ __('We design, build and scale web platforms, mobile apps, SaaS products and AI-powered automation — engineered for reliability and growth.') }}</p>
            <div class="flex flex-wrap gap-3">
                <x-ui.button href="mailto:hello@codefanz.com" size="lg">{{ __('Start Your Project') }}</x-ui.button>
            </div>
        </div>
    </section>
</x-layouts.public>
