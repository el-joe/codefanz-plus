<?php

namespace App\Actions\Users;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use App\Notifications\StaffInvitation;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Creates (invites) or updates a staff account with lock-out and privilege-escalation guards.
 */
class SaveStaffUser
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array{name: string, email: string, locale: string, timezone: string, roles: list<string>, is_active?: bool}  $data
     *
     * @throws ValidationException
     */
    public function handle(?User $user, array $data, User $actor): User
    {
        $this->guardRoles($user, $data['roles'], $actor);

        $isNew = $user === null;
        $isActive = $isNew ? true : (bool) ($data['is_active'] ?? false);

        if (! $isNew && $user->is($actor) && ! $isActive) {
            throw ValidationException::withMessages(['is_active' => __('You cannot deactivate your own account.')]);
        }

        $user = DB::transaction(function () use ($user, $data, $actor, $isNew, $isActive): User {
            $user ??= new User(['password' => Str::password(40)]);

            if (! $isNew) {
                $user->loadMissing('roles');
            }

            $before = $isNew ? null : $this->snapshot($user);

            $user->fill([
                'name' => $data['name'],
                'email' => $data['email'],
                'locale' => $data['locale'],
                'timezone' => $data['timezone'],
                'is_active' => $isActive,
            ])->save();

            $user->syncRoles($data['roles']);

            $this->guardLastSuperAdmin();

            if ($before !== null && $before['is_active'] && ! $isActive) {
                $this->endSessions($user);
            }

            $this->audit->record(
                operation: $isNew ? 'user.invited' : 'user.updated',
                auditable: $user,
                user: $actor,
                before: $before,
                after: $this->snapshot($user->load('roles')),
            );

            return $user;
        });

        if ($isNew) {
            $this->sendInvitation($user);
        }

        return $user;
    }

    public function sendInvitation(User $user): void
    {
        $user->notify(new StaffInvitation(Password::broker()->createToken($user)));
    }

    /**
     * @param  list<string>  $roles
     *
     * @throws ValidationException
     */
    private function guardRoles(?User $user, array $roles, User $actor): void
    {
        $grantsSuperAdmin = in_array(Role::SuperAdmin->value, $roles, true);

        if ($grantsSuperAdmin && ! $actor->hasRole(Role::SuperAdmin->value) && ! $user?->hasRole(Role::SuperAdmin->value)) {
            throw ValidationException::withMessages(['roles' => __('Only a Super Admin can grant the Super Admin role.')]);
        }

        if ($user !== null && $user->is($actor)) {
            $keepsAccess = $grantsSuperAdmin || collect($roles)->contains(
                fn (string $role): bool => in_array(Permission::UsersManage, Role::from($role)->defaultPermissions(), true),
            );

            if (! $keepsAccess) {
                throw ValidationException::withMessages(['roles' => __('You cannot remove your own access to user management.')]);
            }
        }
    }

    /**
     * @throws ValidationException
     */
    private function guardLastSuperAdmin(): void
    {
        $remaining = User::role(Role::SuperAdmin->value)->where('is_active', true)->count();

        if ($remaining === 0) {
            throw ValidationException::withMessages(['roles' => __('At least one active Super Admin is required.')]);
        }
    }

    /**
     * Sign the user out everywhere: database sessions and API tokens.
     */
    private function endSessions(User $user): void
    {
        DB::table(config('session.table', 'sessions'))->where('user_id', $user->getKey())->delete();
        $user->tokens()->delete();
    }

    /**
     * @return array{name: string, email: string, locale: ?string, timezone: ?string, is_active: bool, roles: list<string>}
     */
    private function snapshot(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'locale' => $user->locale,
            'timezone' => $user->timezone,
            'is_active' => (bool) $user->is_active,
            'roles' => $user->roles->pluck('name')->sort()->values()->all(),
        ];
    }
}
