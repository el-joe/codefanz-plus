<x-layouts.admin :title="__('Roles & permissions')" :crumb="__('Users & roles')">
    @include('admin.users._tabs')

    <x-ui.alert tone="info">{{ __('Roles are defined in code and reviewed with each release. Every permission is checked on the server; hiding a button is never the only protection. Super Admin has every permission.') }}</x-ui.alert>

    <x-ui.card padding="p-0" class="overflow-x-auto">
        <table class="w-full text-sm">
            <caption class="sr-only">{{ __('Permission matrix') }}</caption>
            <thead class="bg-subtle text-xs text-fg-tertiary">
                <tr>
                    <th scope="col" class="sticky start-0 bg-subtle px-4 py-3 text-start font-semibold">{{ __('Permission') }}</th>
                    @foreach ($roles as $role)
                        <th scope="col" class="px-3 py-3 text-center font-semibold">
                            <span class="block whitespace-nowrap">{{ $role->label() }}</span>
                            <span class="font-normal">{{ trans_choice(':count user|:count users', $granted[$role->value]->users_count ?? 0) }}</span>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($permissions as $permission)
                    <tr class="border-t border-line-subtle">
                        <th scope="row" class="sticky start-0 bg-surface px-4 py-2.5 text-start font-mono text-xs font-normal text-fg" dir="ltr">{{ $permission->value }}</th>
                        @foreach ($roles as $role)
                            @php($has = $role === \App\Enums\Role::SuperAdmin || ($granted[$role->value] ?? null)?->permissions->contains('name', $permission->value))
                            <td class="px-3 py-2.5 text-center">
                                @if ($has)
                                    <span class="text-fg-success" aria-hidden="true">✓</span><span class="sr-only">{{ __('Granted') }}</span>
                                @else
                                    <span class="text-fg-disabled" aria-hidden="true">–</span><span class="sr-only">{{ __('Not granted') }}</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-ui.card>
</x-layouts.admin>
