@php($email = app(\App\Services\Settings::class)->get('contact', 'email') ?: 'hello@codefanz.com')

<x-layouts.public
    :title="__('Careers at CodeFanz')"
    :description="__('Join a team of engineers and designers who care about craft, clarity and continuous learning.')"
    :alternates="\App\Models\Language::active()->keys()->mapWithKeys(fn ($code) => [$code => route('careers.index', ['locale' => $code])])->all()">
    <x-site.page-hero :eyebrow="__('Careers')" :title="__('Build products people rely on')"
                      :lead="__('Join a team of engineers and designers who care about craft, clarity and continuous learning.')"
                      :crumbs="[__('Careers') => null]" />

    <section class="mx-auto flex max-w-7xl flex-col gap-6 px-5 py-14 lg:px-20 lg:py-20" aria-labelledby="open-positions">
        <h2 id="open-positions" class="text-2xl font-bold text-fg">{{ __('Open positions') }}</h2>
        @if ($careers->isEmpty())
            <x-ui.empty-state class="rounded-xl border border-dashed border-line" :title="__('No open positions right now')" :description="__('We are always happy to hear from talented people. Send us your CV and we will keep it on file.')" />
        @else
            <ul class="flex flex-col gap-3">
                @foreach ($careers as $career)
                    @include('careers._card', ['career' => $career])
                @endforeach
            </ul>
        @endif
    </section>

    <x-site.cta-band :title="__('Don’t see a role that fits?')" :lead="__('Send us your CV and portfolio — we review every application.')"
                     :action="__('Send your CV')" :href="'mailto:'.$email.'?subject='.rawurlencode(__('Open application'))" />
</x-layouts.public>
