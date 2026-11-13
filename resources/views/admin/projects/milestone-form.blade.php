@php
    $options = $project->milestones->reject(fn ($item) => $milestone->exists && $item->is($milestone));
    $selected = array_map('intval', (array) old('dependencies', $selectedDependencies));
@endphp

<x-layouts.admin :title="$milestone->exists ? __('Edit milestone') : __('Add milestone')" :crumb="$project->name">
    @if ($errors->any())
        <x-ui.alert tone="danger" :title="__('Please fix the highlighted fields')">{{ $errors->first('lock_version') ?: $errors->first('dependencies') ?: __('Some fields need attention.') }}</x-ui.alert>
    @endif

    <form method="POST" action="{{ $milestone->exists ? route('admin.projects.milestones.update', [$project, $milestone]) : route('admin.projects.milestones.store', $project) }}" class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
        @csrf
        @if ($milestone->exists)
            @method('PUT')
            <input type="hidden" name="lock_version" value="{{ $milestone->lock_version }}">
        @endif

        <div class="flex min-w-0 flex-col gap-6">
            <x-ui.card :title="__('Milestone')">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2"><x-ui.input name="title" :label="__('Title')" :value="$milestone->title" required maxlength="160" dir="auto" /></div>
                    <x-ui.input name="due_date" type="date" :label="__('Due date')" :value="$milestone->due_date?->toDateString()" />
                </div>
                <div class="mt-4">
                    @include('admin._textarea', ['name' => 'description', 'id' => 'description', 'label' => __('Description and deliverables'), 'value' => $milestone->description, 'rows' => 5, 'max' => 5000, 'help' => __('Shown to the client in the portal.')])
                </div>
            </x-ui.card>

            <x-ui.card :title="__('Depends on')">
                <p class="mb-3 text-sm text-fg-secondary">{{ __('This milestone can only start once the selected milestones are completed.') }}</p>
                @if ($options->isEmpty())
                    <p class="text-sm text-fg-tertiary">{{ __('No other milestones in this project yet.') }}</p>
                @else
                    <ul class="flex flex-col gap-2">
                        @foreach ($options as $option)
                            <li><label class="flex items-center gap-2 text-sm text-fg"><input type="checkbox" name="dependencies[]" value="{{ $option->id }}" @checked(in_array($option->id, $selected, true)) class="size-4 accent-brand"> <span dir="auto">{{ $option->title }}</span></label></li>
                        @endforeach
                    </ul>
                @endif
                @error('dependencies')<p class="mt-2 text-sm text-fg-danger">{{ $message }}</p>@enderror
            </x-ui.card>
        </div>

        <div class="flex flex-col gap-6">
            <x-ui.card :title="__('Amount')">
                <div class="flex flex-col gap-4">
                    <x-ui.input name="amount" type="number" step="0.01" min="0" :label="__('Amount')" :value="\App\Services\Money::toDecimal($milestone->amount_minor)" dir="ltr" />
                    <x-ui.input name="currency" :label="__('Currency')" :value="$milestone->currency" maxlength="3" dir="ltr" required />
                    <p class="text-xs text-fg-tertiary">{{ __('Paid amounts come from verified payments only and cannot be edited here.') }}</p>
                </div>
            </x-ui.card>
            <x-ui.card>
                <div class="flex flex-col gap-3">
                    <x-ui.button>{{ $milestone->exists ? __('Save changes') : __('Add milestone') }}</x-ui.button>
                    <a href="{{ route('admin.projects.show', ['project' => $project, 'tab' => 'milestones']) }}" class="text-center text-sm font-semibold text-fg-brand hover:underline">{{ __('Cancel') }}</a>
                </div>
            </x-ui.card>
        </div>
    </form>
</x-layouts.admin>
