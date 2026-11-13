<section class="dark bg-[linear-gradient(90deg,var(--color-brand-950),var(--color-ink-950))] py-12 text-fg lg:py-18" aria-labelledby="{{ $headingId }}" data-section="statistics">
    <div class="container-page">
        <h2 id="{{ $headingId }}" class="sr-only">{{ $copy['heading'] }}</h2>
        <dl class="grid grid-cols-2 gap-6 {{ [1 => 'lg:grid-cols-1', 2 => 'lg:grid-cols-2', 3 => 'lg:grid-cols-3', 4 => 'lg:grid-cols-4', 5 => 'lg:grid-cols-5'][min(4, max(1, $data->count()))] }}">
            @foreach ($data as $statistic)
                @php($translation = $statistic->exactTranslation(app()->getLocale()))
                <div class="flex flex-col-reverse items-start gap-1 lg:items-center lg:text-center">
                    <dt class="type-body-md text-fg-secondary">{{ $translation->label }}</dt>
                    <dd class="type-h2 text-accent-300 lg:type-display-lg"><span dir="ltr">{{ $statistic->formattedValue() }}{{ $translation->suffix }}</span></dd>
                </div>
            @endforeach
        </dl>
    </div>
</section>
