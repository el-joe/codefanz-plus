<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Optional two-factor authentication for clients. Enabling, confirming and disabling go through Fortify's endpoints.
 */
class SecurityController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('portal.security', ['user' => $request->user()]);
    }
}
