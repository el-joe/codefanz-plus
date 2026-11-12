<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Customers\ManagePortalAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\InvitePortalUserRequest;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Portal logins of one customer: invite, resend invitation, switch access off and on.
 */
class CustomerPortalUserController extends Controller
{
    public function store(InvitePortalUserRequest $request, Customer $customer, ManagePortalAccess $access): RedirectResponse
    {
        $user = $access->invite($customer, $request->validated(), $request->user());

        return back()->with('toast', __('Invitation sent to :email.', ['email' => $user->email]));
    }

    public function resend(Request $request, Customer $customer, User $user, ManagePortalAccess $access): RedirectResponse
    {
        $this->authorizeMember($customer, $user);

        $access->resendInvitation($customer, $user, $request->user());

        return back()->with('toast', __('Invitation sent to :email.', ['email' => $user->email]));
    }

    public function revoke(Request $request, Customer $customer, User $user, ManagePortalAccess $access): RedirectResponse
    {
        $this->authorizeMember($customer, $user);

        $access->revoke($customer, $user, $request->user());

        return back()->with('toast', __('Portal access switched off for :name.', ['name' => $user->name]));
    }

    public function restore(Request $request, Customer $customer, User $user, ManagePortalAccess $access): RedirectResponse
    {
        $this->authorizeMember($customer, $user);

        $access->restore($customer, $user, $request->user());

        return back()->with('toast', __('Portal access restored for :name.', ['name' => $user->name]));
    }

    /**
     * The user must be linked to this customer; anything else is a 404 so ids cannot be probed.
     */
    private function authorizeMember(Customer $customer, User $user): void
    {
        Gate::authorize('managePortalAccess', $customer);

        abort_unless($customer->users()->whereKey($user->getKey())->exists(), 404);
    }
}
