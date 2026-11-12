{{-- "Related projects" section. Expects $projects (cards) and optional $title / $headingId. --}}
@if ($projects->isNotEmpty())
    <section aria-labelledby="{{ $headingId ?? 'related-projects' }}" class="flex flex-col gap-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div class="flex flex-col gap-1">
                <p class="text-xs font-bold uppercase tracking-wider text-fg-brand">{{ __('More work') }}</p>
                <h2 id="{{ $headingId ?? 'related-projects' }}" class="text-2xl font-bold text-fg">{{ $title ?? __('Related projects') }}</h2>
            </div>
            <a href="{{ route('portfolio.index') }}" class="text-sm font-semibold text-fg-brand hover:underline">{{ __('View all projects') }}</a>
        </div>
        <ul class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($projects as $project)
                @include('portfolio._card', ['project' => $project])
            @endforeach
        </ul>
    </section>
@endif
