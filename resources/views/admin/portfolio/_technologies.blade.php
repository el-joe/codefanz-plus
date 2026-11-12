{{-- Technology checkboxes grouped by category. Expects $technologies, $selected (list<int>) and $legend. --}}
<x-ui.card :title="__('Tech stack')">
    @if ($technologies->isEmpty())
        <p class="text-sm text-fg-secondary">{{ __('No technologies yet.') }} <a href="{{ route('admin.technologies.create') }}" class="font-semibold text-fg-brand hover:underline">{{ __('Add a technology') }}</a></p>
    @else
        <fieldset class="flex flex-col gap-4">
            <legend class="mb-3 text-sm text-fg-secondary">{{ $legend }}</legend>
            @foreach ($technologies->groupBy('technology_category_id') as $group)
                <div class="flex flex-col gap-2">
                    <p class="text-xs font-semibold uppercase text-fg-tertiary">{{ $group->first()->category->translation()?->name ?? $group->first()->category->key }}</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($group as $technology)
                            <label class="inline-flex items-center gap-2 rounded-full border border-line px-3 py-1.5 text-sm text-fg has-checked:border-line-brand has-checked:bg-brand-subtle">
                                <input type="checkbox" name="technology_ids[]" value="{{ $technology->id }}" @checked(in_array($technology->id, $selected, true)) class="size-4 accent-brand">
                                {{ $technology->translation()?->name ?? $technology->key }}
                            </label>
                        @endforeach
                    </div>
                </div>
            @endforeach
            @error('technology_ids')<p class="text-sm text-fg-danger">{{ $message }}</p>@enderror
        </fieldset>
    @endif
</x-ui.card>
