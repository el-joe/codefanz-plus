<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Services\CurrentCustomer;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Portal home (P04). Projects, invoices and messages arrive in Milestone 6; until then their cards show empty states.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, CurrentCustomer $currentCustomer): View
    {
        return view('portal.dashboard', [
            'customer' => $currentCustomer->get(),
            'unreadNotificationCount' => $request->user()->unreadNotifications()->count(),
        ]);
    }
}
