{{-- "Related projects" block (Figma "Related Projects"). Expects $projects (cards) and optional $eyebrow / $title / $headingId. --}}
@if ($projects->isNotEmpty())
    <section aria-labelledby="{{ $headingId ?? 'related-projects' }}" class="flex flex-col gap-12">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <x-site.section-heading :id="$headingId ?? 'related-projects'" align="start" :eyebrow="$eyebrow ?? __('More work')" :heading="$title ?? __('Related projects')" />
            <x-ui.button :href="route('portfolio.index')" variant="ghost" arrow>{{ __('View all projects') }}</x-ui.button>
        </div>
        <ul data-reveal-stagger class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($projects as $project)
                @include('portfolio._card', ['project' => $project])
            @endforeach
        </ul>
    </section>
@endif
