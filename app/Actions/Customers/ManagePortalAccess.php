<?php

namespace App\Actions\Customers;

use App\Enums\CustomerUserRole;
use App\Enums\Role;
use App\Models\Customer;
use App\Models\User;
use App\Notifications\ClientInvitation;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Portal users of a customer: invite (new or existing client login), resend the invitation, switch access off and on.
 *
 * A user who loses their last active membership is deactivated and signed out everywhere; restoring access
 * reactivates them.
 */
class ManagePortalAccess
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array{name: string, email: string, locale: string, role: string}  $data
     *
     * @throws ValidationException
     */
    public function invite(Customer $customer, array $data, User $actor): User
    {
        $user = DB::transaction(function () use ($customer, $data, $actor): User {
            $user = User::withTrashed()->with('roles')->where('email', $data['email'])->first();

            if ($user !== null && ($user->trashed() || $user->isStaff())) {
                throw ValidationException::withMessages(['email' => __('This email belongs to a staff account and cannot be invited to the portal.')])->errorBag('invitePortalUser');
            }

            if ($user !== null && $customer->users()->whereKey($user->getKey())->exists()) {
                throw ValidationException::withMessages(['email' => __('This person already has a portal login for this customer.')])->errorBag('invitePortalUser');
            }

            if ($user === null) {
                $user = User::create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'locale' => $data['locale'],
                    'timezone' => config('app.timezone'),
                    'password' => Str::password(40),
                    'is_active' => true,
                ]);
                $user->assignRole(Role::Client->value);
            } else {
                if (! $user->hasRole(Role::Client->value)) {
                    $user->assignRole(Role::Client->value);
                }

                if (! $user->is_active) {
                    $user->forceFill(['is_active' => true])->save();
                }
            }

            $customer->users()->attach($user, ['role' => CustomerUserRole::from($data['role']), 'invited_at' => now()]);

            $this->audit->record(
                operation: 'customer.portal_user_invited',
                auditable: $customer,
                user: $actor,
                after: ['user_id' => $user->getKey(), 'email' => $user->email, 'role' => $data['role']],
            );

            return $user;
        });

        $this->sendInvitation($customer, $user);

        return $user;
    }

    public function resendInvitation(Customer $customer, User $user, User $actor): void
    {
        $customer->users()->updateExistingPivot($user->getKey(), ['invited_at' => now()]);
        $this->sendInvitation($customer, $user);

        $this->audit->record(operation: 'customer.portal_invitation_sent', auditable: $customer, user: $actor, after: ['user_id' => $user->getKey()]);
    }

    public function revoke(Customer $customer, User $user, User $actor): void
    {
        DB::transaction(function () use ($customer, $user, $actor): void {
            $customer->users()->updateExistingPivot($user->getKey(), ['revoked_at' => now()]);

            $stillHasAccess = $user->customers()->wherePivotNull('revoked_at')->exists();

            if (! $stillHasAccess) {
                $user->forceFill(['is_active' => false])->save();
                DB::table(config('session.table', 'sessions'))->where('user_id', $user->getKey())->delete();
                $user->tokens()->delete();
            }

            $this->audit->record(
                operation: 'customer.portal_access_revoked',
                auditable: $customer,
                user: $actor,
                after: ['user_id' => $user->getKey(), 'user_deactivated' => ! $stillHasAccess],
            );
        });
    }

    public function restore(Customer $customer, User $user, User $actor): void
    {
        DB::transaction(function () use ($customer, $user, $actor): void {
            $customer->users()->updateExistingPivot($user->getKey(), ['revoked_at' => null]);
            $user->forceFill(['is_active' => true])->save();

            $this->audit->record(operation: 'customer.portal_access_restored', auditable: $customer, user: $actor, after: ['user_id' => $user->getKey()]);
        });
    }

    private function sendInvitation(Customer $customer, User $user): void
    {
        $user->notify(new ClientInvitation(Password::broker()->createToken($user), $customer->company_name));
    }
}
