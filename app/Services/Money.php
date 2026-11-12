<?php

namespace App\Services;

use App\Models\Language;
use Illuminate\Support\Number;

/**
 * Money is stored as integer minor units + ISO currency (docs/DATABASE_DESIGN.md §1). These helpers convert
 * between form input and minor units and format amounts for people, without floats in storage.
 */
class Money
{
    /**
     * "USD 12,000" style, in the current locale's digits; cents shown only when present.
     */
    public static function format(int $minor, string $currency): string
    {
        $hasFraction = $minor % 100 !== 0;

        return $currency.' '.Number::format($minor / 100, precision: $hasFraction ? 2 : 0, locale: 'en');
    }

    /**
     * Ledger-style amount with both decimals ("USD 4,500.00"). Western digits in every locale; for right-to-left
     * locales the string is wrapped in Unicode left-to-right isolates (U+2066 … U+2069) so the code, separators
     * and digits keep their order inside Arabic text.
     */
    public static function localized(int $minor, string $currency, ?string $locale = null): string
    {
        $text = self::plain($minor, $currency);

        return self::isRightToLeft($locale ?? app()->getLocale()) ? "\u{2066}{$text}\u{2069}" : $text;
    }

    /**
     * Same as localized() without the isolate characters, for places that set dir="ltr" themselves (HTML cells, CSV).
     */
    public static function plain(int $minor, string $currency): string
    {
        return ($minor < 0 ? '-' : '').mb_strtoupper($currency).' '.number_format(abs($minor) / 100, 2, '.', ',');
    }

    /**
     * Convert a validated decimal string ("1250.5") to minor units without float rounding errors.
     */
    public static function toMinor(string|int|float|null $amount): ?int
    {
        if ($amount === null || $amount === '') {
            return null;
        }

        [$units, $fraction] = array_pad(explode('.', (string) $amount, 2), 2, '0');

        return (int) $units * 100 + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }

    /**
     * Minor units as a plain decimal string for form inputs ("1250.50").
     */
    public static function toDecimal(?int $minor): ?string
    {
        return $minor === null ? null : number_format($minor / 100, 2, '.', '');
    }

    private static function isRightToLeft(string $locale): bool
    {
        return (Language::active()[$locale]['direction'] ?? null) === 'rtl';
    }
}
