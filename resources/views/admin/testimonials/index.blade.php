<x-layouts.admin :title="__('Testimonials')" :crumb="__('Content & SEO')">
    <div class="flex flex-wrap items-center gap-3">
        <p class="flex-1 text-sm text-fg-secondary">{{ __('A testimonial can only be published after you confirm the client agreed to publication. Featured testimonials appear on the homepage.') }}</p>
        @can('create', \App\Models\Testimonial::class)
            <x-ui.button :href="route('admin.testimonials.create')">{{ __('New testimonial') }}</x-ui.button>
        @endcan
    </div>

    <x-ui.card padding="p-0" class="overflow-x-auto">
        @if ($testimonials->isEmpty())
            <x-ui.empty-state :title="__('No testimonials yet')" :description="__('Add client quotes once the client has agreed to publication.')" />
        @else
            <table class="w-full text-sm">
                <thead class="bg-subtle text-xs text-fg-tertiary">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Author') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Project') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Consent') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Languages') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Status') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Order') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($testimonials as $testimonial)
                        <tr class="border-t border-line-subtle">
                            <td class="px-4 py-3">
                                <a href="{{ route('admin.testimonials.edit', $testimonial) }}" class="font-semibold text-fg hover:text-fg-brand" dir="auto">{{ $testimonial->author_name }}</a>
                                @if ($testimonial->is_featured)
                                    <x-ui.badge tone="brand" :dot="false">{{ __('Featured') }}</x-ui.badge>
                                @endif
                                @if ($testimonial->company)
                                    <span class="block text-xs text-fg-tertiary" dir="auto">{{ $testimonial->company }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-fg-secondary" dir="auto">{{ $testimonial->project?->translation()?->title ?? '—' }}</td>
                            <td class="px-4 py-3"><x-ui.badge :tone="$testimonial->consent_confirmed ? 'success' : 'warning'">{{ $testimonial->consent_confirmed ? __('Confirmed') : __('Not confirmed') }}</x-ui.badge></td>
                            <td class="px-4 py-3">@include('admin._languages', ['model' => $testimonial])</td>
                            <td class="px-4 py-3"><x-ui.badge :tone="$testimonial->status === \App\Enums\ContentStatus::Published ? 'success' : 'neutral'">{{ $testimonial->status->label() }}</x-ui.badge></td>
                            <td class="px-4 py-3 text-fg-secondary">{{ $testimonial->sort_order }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-ui.card>

    {{ $testimonials->links() }}
</x-layouts.admin>
