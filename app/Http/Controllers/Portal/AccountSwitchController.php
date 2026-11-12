<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\CurrentCustomer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Switches the customer account a portal user acts for. Only customers they hold an active membership in are accepted.
 */
class AccountSwitchController extends Controller
{
    public function __invoke(Request $request, CurrentCustomer $currentCustomer, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate(['customer' => ['required', 'string', 'size:26']]);

        $customer = $currentCustomer->switchTo($request->user(), $validated['customer'], $request->session());

        abort_if($customer === null, 404);

        $audit->record(operation: 'portal.account_switched', auditable: $customer, user: $request->user());

        return redirect()->route('portal.dashboard')->with('toast', __('You are now viewing :customer.', ['customer' => $customer->company_name]));
    }
}
