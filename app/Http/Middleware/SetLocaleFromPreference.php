<?php

namespace App\Http\Middleware;

use App\Models\Language;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the locale for non-prefixed areas (auth, admin, portal): user preference, then session, then browser.
 */
class SetLocaleFromPreference
{
    public function handle(Request $request, Closure $next): Response
    {
        $available = Language::active()->keys()->all();

        $locale = collect([
            $request->user()?->locale,
            $request->session()->get('locale'),
            $request->getPreferredLanguage($available),
        ])->first(fn (?string $candidate): bool => $candidate !== null && in_array($candidate, $available, true));

        app()->setLocale($locale ?? Language::defaultCode());

        return $next($request);
    }
}
