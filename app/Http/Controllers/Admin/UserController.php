<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Users\SaveStaffUser;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveUserRequest;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', User::class);

        $search = trim($request->string('q')->toString());
        $role = Role::tryFrom($request->string('role')->toString());

        return view('admin.users.index', [
            'users' => User::query()
                ->with('roles')
                ->whereDoesntHave('roles', fn ($query) => $query->where('name', Role::Client->value))
                ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")))
                ->when($role !== null, fn ($query) => $query->role($role->value))
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString(),
            'search' => $search,
            'role' => $role,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', User::class);

        return view('admin.users.form', ['user' => new User(['locale' => 'en', 'timezone' => 'Africa/Cairo', 'is_active' => true])]);
    }

    public function store(SaveUserRequest $request, SaveStaffUser $save): RedirectResponse
    {
        $user = $save->handle(null, $request->validated(), $request->user());

        return redirect()->route('admin.users.edit', $user)
            ->with('toast', __('Invitation sent to :email.', ['email' => $user->email]));
    }

    public function edit(User $user): View
    {
        Gate::authorize('update', $user);

        return view('admin.users.form', ['user' => $user->load('roles')]);
    }

    public function update(SaveUserRequest $request, User $user, SaveStaffUser $save): RedirectResponse
    {
        $save->handle($user, $request->validated(), $request->user());

        return redirect()->route('admin.users.edit', $user)->with('toast', __('User saved.'));
    }

    public function resendInvitation(Request $request, User $user, SaveStaffUser $save, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('update', $user);

        $save->sendInvitation($user);
        $audit->record(operation: 'user.invitation_sent', auditable: $user, user: $request->user());

        return back()->with('toast', __('Invitation sent to :email.', ['email' => $user->email]));
    }

    /**
     * Clears two-factor for a colleague who lost their device; they must set it up again at next sign-in.
     */
    public function resetTwoFactor(Request $request, User $user, AuditLogger $audit): RedirectResponse
    {
        // Enforced here as well because Super Admin bypasses policies through Gate::before.
        abort_if($user->is($request->user()), 403, __('Reset your own two-factor from the Security page.'));
        Gate::authorize('resetTwoFactor', $user);

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $audit->record(operation: 'user.two_factor_reset', auditable: $user, user: $request->user());

        return back()->with('toast', __('Two-factor authentication was reset. :name must set it up again.', ['name' => $user->name]));
    }
}
