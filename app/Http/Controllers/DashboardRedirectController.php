<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardRedirectController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        if ($request->user()->can(Permission::AdminAccess->value)) {
            return redirect()->route('admin.dashboard');
        }

        return redirect()->route('home', ['locale' => app()->getLocale()])
            ->with('toast', __('The client portal opens with your first project.'));
    }
}
