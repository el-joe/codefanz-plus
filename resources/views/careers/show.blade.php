@php
    $locale = app()->getLocale();
    $settings = app(\App\Services\Settings::class);
    $email = $settings->get('contact', 'email') ?: 'hello@codefanz.com';
    $company = ($settings->get('general', 'company_name') ?? [])[$locale] ?? 'CodeFanz';
    $applyHref = 'mailto:'.$email.'?subject='.rawurlencode(__('Application: :position', ['position' => $translation->title]));
    $prose = 'flex flex-col gap-4 type-body-md text-fg-secondary [&_a]:text-fg-brand [&_a]:underline [&_h2]:mt-4 [&_h2]:type-h4 [&_h2]:text-fg [&_h3]:type-h5 [&_h3]:text-fg [&_ol]:list-decimal [&_ol]:ps-6 [&_strong]:text-fg [&_ul]:list-disc [&_ul]:ps-6';
    $facts = array_filter([
        __('Department') => $translation->department,
        __('Location') => $translation->location,
        __('Employment type') => $career->employment_type->label(),
        __('Remote') => $career->is_remote ? __('Yes') : null,
        __('Apply by') => $career->closes_at?->translatedFormat('j F Y'),
    ]);
    $jobPosting = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'JobPosting',
        'title' => $translation->title,
        'description' => trim($body) !== '' ? $body : e($translation->summary ?? $translation->title),
        'datePosted' => ($career->published_at ?? $career->created_at)->toDateString(),
        'validThrough' => $career->closes_at?->copy()->endOfDay()->toIso8601String(),
        'employmentType' => $career->employment_type->schemaValue(),
        'hiringOrganization' => ['@type' => 'Organization', 'name' => $company, 'sameAs' => route('home')],
        'jobLocation' => filled($translation->location) ? ['@type' => 'Place', 'address' => ['@type' => 'PostalAddress', 'addressLocality' => $translation->location]] : null,
        'jobLocationType' => $career->is_remote ? 'TELECOMMUTE' : null,
        'identifier' => ['@type' => 'PropertyValue', 'name' => $company, 'value' => (string) $career->id],
        'directApply' => false,
        'url' => url()->current(),
    ], fn ($value) => $value !== null);
@endphp

<x-layouts.public
    :title="$translation->seo_title ?: $translation->title"
    :description="$translation->seo_description ?: ($translation->summary ?: $translation->title)"
    :seoable="$career"
    :alternates="$career->translations->mapWithKeys(fn ($alt) => [$alt->locale => route('careers.show', ['locale' => $alt->locale, 'slug' => $alt->slug])])->all()">
    <x-site.page-hero :title="$translation->title" :lead="$translation->summary" :crumbs="[__('Careers') => route('careers.index'), $translation->title => null]">
        <x-slot:before>
            <p class="flex flex-wrap gap-2">
                @if ($translation->department)
                    <x-ui.badge tone="brand" :dot="false">{{ $translation->department }}</x-ui.badge>
                @endif
                <x-ui.badge :dot="false">{{ $career->employment_type->label() }}</x-ui.badge>
                @if ($career->is_remote)
                    <x-ui.badge tone="accent" :dot="false">{{ __('Remote') }}</x-ui.badge>
                @endif
            </p>
        </x-slot:before>
        <div class="flex flex-wrap gap-3">
            <x-ui.button :href="$applyHref" size="lg" arrow>{{ __('Apply by email') }}</x-ui.button>
            <x-ui.button :href="route('contact', ['topic' => 'careers', 'ref' => $translation->title])" variant="secondary" size="lg">{{ __('Ask a question') }}</x-ui.button>
        </div>
    </x-site.page-hero>

    <x-site.section tone="surface" inner="grid items-start gap-10 lg:grid-cols-[minmax(0,1fr)_22.5rem] lg:gap-16">
        <div class="flex min-w-0 flex-col gap-10">
            @if (trim($body) !== '')
                <div class="{{ $prose }}">{!! $body !!}</div>
            @endif
            @foreach (['responsibilities' => __('What you will do'), 'requirements' => __('What we are looking for')] as $list => $heading)
                @if ($translation->{$list})
                    <section aria-labelledby="{{ $list }}" class="flex flex-col gap-4">
                        <h2 id="{{ $list }}" class="type-h3 text-fg">{{ $heading }}</h2>
                        <ul class="flex flex-col gap-3 type-body-md text-fg-secondary">
                            @foreach ($translation->{$list} as $line)
                                <li class="flex items-start gap-2.5">
                                    <x-ui.check-circle class="mt-0.5" />
                                    {{ $line }}
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            @endforeach
        </div>

        <aside class="lg:sticky lg:top-24 lg:self-start" aria-labelledby="position-facts">
            <div class="flex flex-col gap-5 rounded-3xl border border-v2-hairline bg-surface p-6 shadow-v2-card">
                <h2 id="position-facts" class="type-h5 text-fg">{{ __('About this role') }}</h2>
                <dl class="flex flex-col divide-y divide-line-subtle type-body-sm">
                    @foreach ($facts as $label => $value)
                        <div class="flex justify-between gap-4 py-2.5">
                            <dt class="text-fg-tertiary">{{ $label }}</dt>
                            <dd class="text-end type-label-md text-fg">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
                <x-ui.button :href="$applyHref" size="lg" class="w-full" arrow>{{ __('Send your CV') }}</x-ui.button>
                <p class="type-body-xs text-fg-tertiary">{{ __('Email your CV and portfolio to :email. Online applications are coming soon.', ['email' => $email]) }}</p>
            </div>
        </aside>
    </x-site.section>

    <script type="application/ld+json">@json($jobPosting, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)</script>
</x-layouts.public>
