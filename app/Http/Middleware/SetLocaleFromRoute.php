<?php

namespace App\Http\Middleware;

use App\Models\Language;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the {locale} route prefix for public pages and makes it the default for generated URLs.
 */
class SetLocaleFromRoute
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = (string) $request->route('locale');

        abort_unless(Language::active()->has($locale), 404);

        app()->setLocale($locale);
        URL::defaults(['locale' => $locale]);
        $request->session()->put('locale', $locale);
        $request->route()->forgetParameter('locale');

        return $next($request);
    }
}
