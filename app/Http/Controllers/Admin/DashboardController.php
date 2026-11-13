<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ChangeRequestStatus;
use App\Enums\KillSwitchKey;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ChangeRequest;
use App\Services\KillSwitches;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin overview. The approval queue and the audit trail are only loaded for staff allowed to see them
 * (approval permissions / audit.view); everyone else with admin access gets the empty states.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, KillSwitches $killSwitches): View
    {
        $user = $request->user();
        $canReview = $user->can('viewAny', ChangeRequest::class);
        $pending = fn () => ChangeRequest::query()->whereIn('status', [ChangeRequestStatus::Pending, ChangeRequestStatus::UnderReview]);

        return view('admin.dashboard', [
            'pendingApprovals' => $canReview ? $pending()->latest('id')->limit(5)->get() : collect(),
            'pendingCount' => $canReview ? $pending()->count() : 0,
            'recentActivity' => $user->can(Permission::AuditView->value) ? AuditLog::query()->latest('id')->limit(6)->get() : collect(),
            'activeKillSwitches' => collect(KillSwitchKey::cases())->filter(fn (KillSwitchKey $key): bool => $killSwitches->isActive($key))->values(),
        ]);
    }
}
