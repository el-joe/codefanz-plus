<?php

namespace App\Services;

use Illuminate\Http\Request;

/**
 * Picks the language of an error page without the database or a fresh session: the URL prefix, then the
 * signed-in user's preference (only when already loaded), then a started session, then the browser, then the
 * default (config list).
 */
class ErrorPageLocale
{
    /**
     * @return array{code: string, direction: string}
     */
    public static function apply(Request $request): array
    {
        $languages = collect(config('codefanz.languages'))->keyBy('code');
        $default = $languages->firstWhere('is_default', true)['code'] ?? config('app.locale');
        $sessionLocale = $request->hasSession() && $request->session()->isStarted() ? $request->session()->get('locale') : null;

        $locale = collect([
            $request->segment(1),
            auth()->hasUser() ? auth()->user()->locale : null,
            $sessionLocale,
            $request->getPreferredLanguage($languages->keys()->all()),
        ])->first(fn (mixed $candidate): bool => is_string($candidate) && $languages->has($candidate)) ?? $default;

        app()->setLocale($locale);

        return ['code' => $locale, 'direction' => $languages[$locale]['direction'] ?? 'ltr'];
    }
}
