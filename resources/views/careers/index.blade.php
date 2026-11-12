@php($email = app(\App\Services\Settings::class)->get('contact', 'email') ?: 'hello@codefanz.com')

<x-layouts.public
    :title="__('Careers at CodeFanz')"
    :description="__('Join a team of engineers and designers who care about craft, clarity and continuous learning.')"
    :alternates="\App\Models\Language::active()->keys()->mapWithKeys(fn ($code) => [$code => route('careers.index', ['locale' => $code])])->all()">
    <x-site.page-hero :eyebrow="__('Careers')" :title="__('Build products people rely on')"
                      :lead="__('Join a team of engineers and designers who care about craft, clarity and continuous learning.')"
                      :crumbs="[__('Careers') => null]" />

    @if ($perks)
        @php($icons = ['globe', 'trending-up', 'shield', 'sparkles', 'users', 'heart-pulse', 'graduation-cap', 'layers'])
        <section class="mx-auto flex max-w-7xl flex-col gap-8 px-5 pt-14 lg:px-20 lg:pt-20" aria-labelledby="careers-perks" data-section="careers_perks">
            <x-site.section-heading id="careers-perks" align="start" :eyebrow="$perks['eyebrow']" :heading="$perks['heading']" :subheading="$perks['subheading']" />
            <ul class="grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($perks['items'] as $perk)
                    <li class="flex flex-col gap-2">
                        <span class="flex size-10 items-center justify-center rounded-lg bg-brand-subtle text-fg-brand"><x-ui.icon :name="$icons[$loop->index % count($icons)]" /></span>
                        <h3 class="text-base font-semibold text-fg">{{ $perk['title'] }}</h3>
                        @if (filled($perk['text'] ?? null))
                            <p class="text-sm text-fg-secondary">{{ $perk['text'] }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

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

    <p class="mx-auto max-w-7xl px-5 pb-14 text-sm text-fg-secondary lg:px-20">
        {{ __('CVs are sent by email so you can attach files.') }}
        <a href="{{ route('contact', ['topic' => 'careers']) }}" class="font-semibold text-fg-brand hover:underline">{{ __('Have a question about working with us? Send us a message.') }}</a>
    </p>
</x-layouts.public>
