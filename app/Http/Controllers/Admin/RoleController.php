<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Spatie\Permission\Models\Role as RoleModel;

/**
 * Read-only permission matrix. Permissions are enforced on the server and defined in code (App\Enums\Role).
 */
class RoleController extends Controller
{
    public function __invoke(): View
    {
        Gate::authorize('viewAny', User::class);

        $granted = RoleModel::query()
            ->with('permissions:id,name')
            ->withCount('users')
            ->get()
            ->keyBy('name');

        return view('admin.users.roles', [
            'roles' => Role::staff(),
            'permissions' => Permission::cases(),
            'granted' => $granted,
        ]);
    }
}
