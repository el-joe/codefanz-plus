<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ChangeRequestStatus;
use App\Enums\KillSwitchKey;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ChangeRequest;
use App\Services\KillSwitches;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(KillSwitches $killSwitches): View
    {
        return view('admin.dashboard', [
            'pendingApprovals' => ChangeRequest::query()
                ->whereIn('status', [ChangeRequestStatus::Pending, ChangeRequestStatus::UnderReview])
                ->latest('id')
                ->limit(5)
                ->get(),
            'pendingCount' => ChangeRequest::query()
                ->whereIn('status', [ChangeRequestStatus::Pending, ChangeRequestStatus::UnderReview])
                ->count(),
            'recentActivity' => AuditLog::query()->latest('id')->limit(6)->get(),
            'activeKillSwitches' => collect(KillSwitchKey::cases())->filter(fn (KillSwitchKey $key): bool => $killSwitches->isActive($key))->values(),
        ]);
    }
}
