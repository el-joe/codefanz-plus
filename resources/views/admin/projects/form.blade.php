@php
    $memberIds = old('members', $project->exists ? $project->members->pluck('id')->all() : []);
    $memberRoles = old('member_roles', $project->exists ? $project->members->mapWithKeys(fn ($member) => [$member->id => $member->pivot->role])->all() : []);
@endphp

<x-layouts.admin :title="$project->exists ? __('Edit :reference', ['reference' => $project->reference]) : __('New project')" :crumb="__('Projects')">
    @if ($errors->any())
        <x-ui.alert tone="danger" :title="__('Please fix the highlighted fields')">{{ $errors->first('lock_version') ?: $errors->first('customer') ?: __('Some fields need attention.') }}</x-ui.alert>
    @endif

    @if ($lead)
        <x-ui.alert tone="info" :title="__('Starting from a won lead')">
            {{ __('Details are prefilled from lead :reference. Review them before saving.', ['reference' => $lead->reference]) }}
        </x-ui.alert>
    @endif

    <form method="POST" action="{{ $project->exists ? route('admin.projects.update', $project) : route('admin.projects.store') }}" class="grid gap-6 xl:grid-cols-[1fr_340px]">
        @csrf
        @if ($project->exists)
            @method('PUT')
            <input type="hidden" name="lock_version" value="{{ $project->lock_version }}">
        @endif
        @if ($lead)
            <input type="hidden" name="lead" value="{{ $lead->ulid }}">
        @endif

        <div class="flex min-w-0 flex-col gap-6">
            <x-ui.card :title="__('Project')">
                <div class="grid gap-4 sm:grid-cols-2">
                    @if ($project->exists)
                        <div class="flex flex-col gap-1.5">
                            <span class="text-sm font-semibold text-fg">{{ __('Customer') }}</span>
                            <p class="text-sm text-fg-secondary" dir="auto">{{ $customer->company_name }}</p>
                        </div>
                    @elseif ($customer)
                        <div class="flex flex-col gap-1.5">
                            <span class="text-sm font-semibold text-fg">{{ __('Customer') }}</span>
                            <p class="text-sm text-fg-secondary" dir="auto">{{ $customer->company_name }}</p>
                            <input type="hidden" name="customer" value="{{ $customer->ulid }}">
                        </div>
                    @else
                        <x-ui.select name="customer" :label="__('Customer')" :options="$customers" :placeholder="__('Choose a customer')" required />
                    @endif
                    <x-ui.input name="name" :label="__('Project name')" :value="$project->name" required maxlength="160" dir="auto" />
                    <x-ui.select name="type" :label="__('Project type')" :options="\App\Enums\ProjectType::options()" :value="$project->type?->value" />
                    <x-ui.select name="status" :label="__('Status')" :options="\App\Enums\ProjectStatus::options()" :value="$project->status?->value" />
                    <x-ui.input name="start_date" type="date" :label="__('Start date')" :value="$project->start_date?->toDateString()" />
                    <x-ui.input name="due_date" type="date" :label="__('Expected delivery')" :value="$project->due_date?->toDateString()" />
                </div>
                <div class="mt-4">
                    @include('admin._textarea', ['name' => 'description', 'id' => 'description', 'label' => __('Description'), 'value' => $project->description, 'rows' => 4, 'max' => 5000, 'help' => __('Shown to the client in the portal.')])
                </div>
            </x-ui.card>

            <x-ui.card :title="__('Team')">
                <div class="mb-4 sm:w-1/2">
                    <x-ui.select name="project_manager_id" :label="__('Project manager')" :options="$staff->pluck('name', 'id')" :value="$project->project_manager_id" :placeholder="__('Unassigned')" />
                </div>
                <fieldset>
                    <legend class="mb-2 text-sm font-semibold text-fg">{{ __('Team members') }}</legend>
                    <ul class="grid gap-2 sm:grid-cols-2" data-project-members>
                        @foreach ($staff as $member)
                            <li class="flex items-center gap-3 rounded-md border border-line px-3 py-2">
                                <input type="checkbox" id="member-{{ $member->id }}" name="members[]" value="{{ $member->id }}" @checked(in_array($member->id, array_map('intval', (array) $memberIds), true)) class="size-4 accent-brand">
                                <label for="member-{{ $member->id }}" class="min-w-0 flex-1 truncate text-sm text-fg" dir="auto">{{ $member->name }}</label>
                                <input type="text" name="member_roles[{{ $member->id }}]" value="{{ $memberRoles[$member->id] ?? '' }}" maxlength="80" placeholder="{{ __('Role') }}" aria-label="{{ __('Role of :name', ['name' => $member->name]) }}" dir="auto"
                                       class="w-32 rounded-md border border-line bg-surface px-2 py-1 text-xs text-fg">
                            </li>
                        @endforeach
                    </ul>
                    @error('members')<p class="mt-2 text-sm text-fg-danger">{{ $message }}</p>@enderror
                </fieldset>
            </x-ui.card>
        </div>

        <aside class="flex flex-col gap-6">
            <x-ui.card :title="__('Budget')">
                <div class="flex flex-col gap-4">
                    <x-ui.input name="budget" type="number" step="0.01" min="0" :label="__('Total project value')" :value="\App\Services\Money::toDecimal($project->budget_minor)" dir="ltr" />
                    <x-ui.input name="currency" :label="__('Currency')" :value="$project->currency" maxlength="3" dir="ltr" required />
                </div>
            </x-ui.card>

            <x-ui.card :title="__('Shown to the client')">
                <p class="mb-3 text-sm text-fg-secondary">{{ __('Name, status, dates, milestones and shared updates are always visible in the portal.') }}</p>
                <div class="flex flex-col gap-3">
                    <input type="hidden" name="show_budget_to_client" value="0">
                    <label class="flex items-center gap-2 text-sm text-fg"><input type="checkbox" name="show_budget_to_client" value="1" @checked(old('show_budget_to_client', $project->show_budget_to_client)) class="size-4 accent-brand"> {{ __('Project value and milestone amounts') }}</label>
                    <input type="hidden" name="show_team_to_client" value="0">
                    <label class="flex items-center gap-2 text-sm text-fg"><input type="checkbox" name="show_team_to_client" value="1" @checked(old('show_team_to_client', $project->show_team_to_client)) class="size-4 accent-brand"> {{ __('Project manager and team') }}</label>
                </div>
            </x-ui.card>

            <x-ui.card>
                <div class="flex flex-col gap-3">
                    <x-ui.button>{{ $project->exists ? __('Save changes') : __('Create project') }}</x-ui.button>
                    <a href="{{ $project->exists ? route('admin.projects.show', $project) : route('admin.projects.index') }}" class="text-center text-sm font-semibold text-fg-brand hover:underline">{{ __('Cancel') }}</a>
                </div>
            </x-ui.card>
        </aside>
    </form>
</x-layouts.admin>
