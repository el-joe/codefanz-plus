<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Own profile and password. Updates go through Fortify's endpoints (see App\Actions\Fortify).
 */
class ProfileController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('admin.profile', ['user' => $request->user()]);
    }
}
