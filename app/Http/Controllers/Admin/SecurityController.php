<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Two-factor setup page. Enabling, confirming and disabling go through Fortify's endpoints.
 */
class SecurityController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('admin.security', ['user' => $request->user()]);
    }
}
