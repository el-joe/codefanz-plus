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
        <x-site.section tone="surface" inner="flex flex-col gap-10" aria-labelledby="careers-perks" data-section="careers_perks">
            <div class="flex flex-col gap-3">
                @if (filled($perks['eyebrow'] ?? null))
                    <p class="type-eyebrow text-fg-brand">{{ $perks['eyebrow'] }}</p>
                @endif
                <h2 id="careers-perks" class="type-h3 text-fg">{{ $perks['heading'] }}</h2>
                @if (filled($perks['subheading'] ?? null))
                    <p class="max-w-3xl type-body-lg text-fg-secondary">{{ $perks['subheading'] }}</p>
                @endif
            </div>
            <ul data-reveal-stagger class="grid grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($perks['items'] as $perk)
                    <li class="flex flex-col gap-2">
                        <span class="mb-2 flex size-10 items-center justify-center rounded-lg bg-brand-subtle text-icon-brand"><x-ui.icon :name="$icons[$loop->index % count($icons)]" /></span>
                        <h3 class="type-label-lg text-fg">{{ $perk['title'] }}</h3>
                        @if (filled($perk['text'] ?? null))
                            <p class="type-body-sm text-fg-secondary">{{ $perk['text'] }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        </x-site.section>
    @endif

    <x-site.section tone="canvas" inner="flex flex-col gap-6" aria-labelledby="open-positions">
        <h2 id="open-positions" class="type-h3 text-fg">{{ __('Open positions') }}</h2>
        @if ($careers->isEmpty())
            <x-ui.empty-state class="rounded-xl border border-dashed border-line bg-surface" :title="__('No open positions right now')" :description="__('We are always happy to hear from talented people. Send us your CV and we will keep it on file.')" />
        @else
            <ul class="flex flex-col gap-3">
                @foreach ($careers as $career)
                    @include('careers._card', ['career' => $career])
                @endforeach
            </ul>
        @endif
        <p class="type-body-sm text-fg-secondary">
            {{ __('CVs are sent by email so you can attach files.') }}
            <a href="{{ route('contact', ['topic' => 'careers']) }}" class="type-label-md text-fg-brand hover:underline">{{ __('Have a question about working with us? Send us a message.') }}</a>
        </p>
    </x-site.section>

    <x-site.cta-band tone="canvas" :title="__('Don’t see a role that fits?')" :lead="__('Send us your CV and portfolio — we review every application.')"
                     :action="__('Send your CV')" :href="'mailto:'.$email.'?subject='.rawurlencode(__('Open application'))" />

</x-layouts.public>
