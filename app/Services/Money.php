<?php

namespace App\Services;

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
}
