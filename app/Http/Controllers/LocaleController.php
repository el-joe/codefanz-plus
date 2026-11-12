<?php

namespace App\Http\Controllers;

use App\Models\Language;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Switches the interface language for non-prefixed areas (admin, portal, auth).
 */
class LocaleController extends Controller
{
    public function __invoke(Request $request, string $code): RedirectResponse
    {
        abort_unless(Language::active()->has($code), 404);

        $request->session()->put('locale', $code);
        $request->user()?->forceFill(['locale' => $code])->save();

        return back();
    }
}
