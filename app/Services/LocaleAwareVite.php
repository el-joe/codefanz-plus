<?php

namespace App\Services;

use App\Models\Language;
use Illuminate\Foundation\Vite;

/**
 * Vite with two font-loading fixes for @fonts (M11 performance):
 *
 * - Only the families the current script direction renders are preloaded (Plus Jakarta Sans for LTR, Cairo for
 *   RTL); preloading every family made English pages download Arabic and monospace fonts they never use.
 * - The font plugin emits a separate @font-face for the WOFF fallback of every WOFF2 file with the same
 *   unicode-range, so browsers picked the later, larger WOFF file and ignored the preloaded WOFF2. Every
 *   supported browser reads WOFF2, so the WOFF rules are dropped.
 */
class LocaleAwareVite extends Vite
{
    /**
     * Font aliases preloaded per text direction.
     *
     * @var array<string, list<string>>
     */
    public const PRELOAD_FAMILIES = [
        'ltr' => ['plus-jakarta-sans'],
        'rtl' => ['cairo'],
    ];

    /**
     * @param  list<array<string, string>>  $preloads
     * @return string
     */
    protected function renderFontPreloads($preloads)
    {
        $direction = Language::active()[app()->getLocale()]['direction'] ?? 'ltr';
        $families = self::PRELOAD_FAMILIES[$direction] ?? self::PRELOAD_FAMILIES['ltr'];

        return parent::renderFontPreloads(array_values(array_filter(
            $preloads,
            fn (array $preload): bool => in_array($preload['alias'] ?? null, $families, true),
        )));
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @param  list<string>|null  $aliases
     * @return string
     */
    protected function renderFontStyle($manifest, $aliases)
    {
        return (string) preg_replace('/@font-face\s*\{[^}]*format\("woff"\)[^}]*\}\s*/', '', parent::renderFontStyle($manifest, $aliases));
    }
}
