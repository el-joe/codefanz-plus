<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Services\CurrentCustomer;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Placeholder for portal modules that arrive in Milestone 6. Shows an honest "nothing here yet" state, never sample data.
 */
class ModuleController extends Controller
{
    /**
     * @var list<string>
     */
    public const MODULES = ['projects', 'invoices', 'payments', 'documents', 'conversations', 'support'];

    public function __invoke(Request $request, CurrentCustomer $currentCustomer): View
    {
        $module = (string) $request->route()->defaults['module'];

        abort_unless(in_array($module, self::MODULES, true), 404);

        return view('portal.module', ['module' => $module, 'customer' => $currentCustomer->get()]);
    }
}
