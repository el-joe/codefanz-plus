<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Services\CurrentCustomer;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Profile & Settings (P14): own details and password. Updates go through Fortify's endpoints (see App\Actions\Fortify).
 */
class ProfileController extends Controller
{
    public function __invoke(Request $request, CurrentCustomer $currentCustomer): View
    {
        $customer = $currentCustomer->get();

        return view('portal.profile', [
            'user' => $request->user(),
            'customer' => $customer,
            'membership' => $currentCustomer->available()->firstWhere('id', $customer->id)?->pivot,
        ]);
    }
}
