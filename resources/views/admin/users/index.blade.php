<x-layouts.admin :title="__('Users')" :crumb="__('Users & roles')">
    <div class="flex flex-wrap items-center justify-between gap-4">
        @include('admin.users._tabs')
        <x-ui.button :href="route('admin.users.create')">{{ __('Invite user') }}</x-ui.button>
    </div>

    <form method="GET" action="{{ route('admin.users.index') }}" class="flex flex-wrap items-end gap-3" role="search">
        <div class="w-full sm:w-72">
            <x-ui.input name="q" type="search" :label="__('Search')" :value="$search" :placeholder="__('Name or email')" />
        </div>
        <div class="w-full sm:w-56">
            <x-ui.select name="role" :label="__('Role')" :value="$role?->value" :placeholder="__('All roles')"
                         :options="collect(\App\Enums\Role::staff())->mapWithKeys(fn ($role) => [$role->value => $role->label()])" />
        </div>
        <x-ui.button variant="secondary">{{ __('Filter') }}</x-ui.button>
    </form>

    <x-ui.card padding="p-0" class="overflow-x-auto">
        @if ($users->isEmpty())
            <x-ui.empty-state :title="__('No users found')" :description="__('Try another search or invite a colleague.')" />
        @else
            <table class="w-full text-sm">
                <thead class="bg-subtle text-xs text-fg-tertiary">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Name') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Roles') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Two-factor') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Status') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Last sign-in') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($users as $user)
                        <tr class="border-t border-line-subtle">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-brand-subtle text-xs font-semibold text-fg-brand" aria-hidden="true">{{ $user->initials() }}</span>
                                    <div class="min-w-0">
                                        @can('update', $user)
                                            <a href="{{ route('admin.users.edit', $user) }}" class="font-semibold text-fg hover:text-fg-brand" dir="auto">{{ $user->name }}</a>
                                        @else
                                            <span class="font-semibold text-fg" dir="auto">{{ $user->name }}</span>
                                        @endcan
                                        <p class="truncate text-xs text-fg-tertiary" dir="ltr">{{ $user->email }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="flex flex-wrap gap-1.5">
                                    @foreach ($user->roles as $assigned)
                                        <x-ui.badge tone="brand" :dot="false">{{ \App\Enums\Role::tryFrom($assigned->name)?->label() ?? $assigned->name }}</x-ui.badge>
                                    @endforeach
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                @if ($user->hasConfirmedTwoFactor())
                                    <x-ui.badge tone="success">{{ __('Enabled') }}</x-ui.badge>
                                @elseif ($user->requiresTwoFactor())
                                    <x-ui.badge tone="warning">{{ __('Required, not set up') }}</x-ui.badge>
                                @else
                                    <x-ui.badge>{{ __('Off') }}</x-ui.badge>
                                @endif
                            </td>
                            <td class="px-4 py-3"><x-ui.badge :tone="$user->is_active ? 'success' : 'danger'">{{ $user->is_active ? __('Active') : __('Deactivated') }}</x-ui.badge></td>
                            <td class="px-4 py-3 text-fg-secondary">{{ $user->last_login_at?->diffForHumans() ?? __('Never') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-ui.card>

    {{ $users->links() }}
</x-layouts.admin>
