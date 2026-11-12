<section class="bg-[linear-gradient(110deg,var(--color-brand-950),var(--color-ink-950))] text-white" aria-labelledby="{{ $headingId }}" data-section="statistics">
    <div class="mx-auto flex max-w-7xl flex-col gap-8 px-5 py-14 lg:px-20">
        <h2 id="{{ $headingId }}" class="sr-only">{{ $copy['heading'] }}</h2>
        <dl class="grid grid-cols-2 gap-8 {{ [1 => 'lg:grid-cols-1', 2 => 'lg:grid-cols-2', 3 => 'lg:grid-cols-3', 4 => 'lg:grid-cols-4', 5 => 'lg:grid-cols-5'][min(4, max(1, $data->count()))] }}">
            @foreach ($data as $statistic)
                @php($translation = $statistic->exactTranslation(app()->getLocale()))
                <div class="flex flex-col-reverse items-center gap-1 text-center">
                    <dt class="text-sm text-white/75">{{ $translation->label }}</dt>
                    <dd class="text-4xl font-extrabold text-accent-300 lg:text-5xl"><span dir="ltr">{{ $statistic->formattedValue() }}{{ $translation->suffix }}</span></dd>
                </div>
            @endforeach
        </dl>
    </div>
</section>
