<?php

namespace App\Http\Controllers;

use App\Services\Analytics\AnalyticsConsent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * No-JavaScript fallback for the cookie banner: stores the visitor's choice and returns to the same page
 * (same-site paths only). With JavaScript the banner writes the same cookie itself.
 */
class ConsentController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $choice = $request->validate([
            'choice' => ['required', Rule::in([AnalyticsConsent::ANALYTICS, AnalyticsConsent::NECESSARY])],
        ])['choice'];

        $referer = (string) $request->headers->get('referer');
        $path = parse_url($referer, PHP_URL_HOST) === $request->getHost() ? (string) parse_url($referer, PHP_URL_PATH) : '/';
        $path = str_starts_with($path, '/') && ! str_starts_with($path, '//') ? $path : '/';

        return redirect($path)->withCookie(cookie(
            AnalyticsConsent::COOKIE,
            $choice,
            60 * 24 * AnalyticsConsent::LIFETIME_DAYS,
            '/',
            null,
            $request->isSecure(),
            false,
            false,
            'lax',
        ));
    }
}
