<section class="mx-auto grid max-w-7xl gap-10 px-5 py-16 lg:grid-cols-2 lg:items-center lg:px-20 lg:py-24" aria-labelledby="{{ $headingId }}" data-section="about_story">
    <div @class(['flex flex-col gap-4', 'lg:col-span-2 lg:max-w-3xl' => $statistics->isEmpty()])>
        <x-site.section-heading :id="$headingId" align="start" :eyebrow="$copy['eyebrow']" :heading="$copy['heading']" :subheading="$copy['subheading']" />
    </div>
    @if ($statistics->isNotEmpty())
        <div class="flex flex-col gap-4 rounded-2xl bg-[linear-gradient(140deg,var(--color-brand-700),var(--color-accent-700))] p-6 sm:p-10">
            <dl class="grid gap-4 sm:grid-cols-2">
                @foreach ($statistics as $statistic)
                    @php($translation = $statistic->exactTranslation(app()->getLocale()))
                    <div class="flex flex-col-reverse gap-1 rounded-xl bg-surface p-5 shadow-lg">
                        <dt class="text-sm text-fg-secondary">{{ $translation->label }}</dt>
                        <dd class="text-3xl font-extrabold text-fg"><span dir="ltr">{{ $statistic->formattedValue() }}{{ $translation->suffix }}</span></dd>
                    </div>
                @endforeach
            </dl>
        </div>
    @endif
</section>
