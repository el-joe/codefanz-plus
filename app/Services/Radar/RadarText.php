<?php

namespace App\Services\Radar;

/**
 * Small text helpers shared by the parsers, the scorer and the filters.
 */
class RadarText
{
    /**
     * "ar" when at least 30% of the letters are Arabic, otherwise "en".
     */
    public static function detectLanguage(string $text): string
    {
        $letters = preg_match_all('/\p{L}/u', $text);
        $arabic = preg_match_all('/\p{Arabic}/u', $text);

        return $letters > 0 && $arabic / $letters >= 0.3 ? 'ar' : 'en';
    }

    /**
     * Lower-case text with Arabic-Indic digits normalised, for keyword matching.
     */
    public static function normalize(string $text): string
    {
        return mb_strtolower(strtr($text, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']));
    }

    /**
     * Whole-word (or whole-phrase) match, so "php" does not match "phpmyadmin" but "vue.js" still matches.
     */
    public static function containsTerm(string $haystack, string $term): bool
    {
        $term = trim(self::normalize($term));

        if ($term === '') {
            return false;
        }

        return preg_match('/(?<![\p{L}\p{N}])'.preg_quote($term, '/').'(?![\p{L}\p{N}])/u', self::normalize($haystack)) === 1;
    }

    /**
     * Amount like "1,500.50" or "1٬500" as minor units.
     */
    public static function toMinor(string $amount): ?int
    {
        $amount = str_replace([',', '٬', ' '], '', self::normalize($amount));
        $amount = str_replace('٫', '.', $amount);

        return is_numeric($amount) ? (int) round(((float) $amount) * 100) : null;
    }

    public static function clean(string $text, int $max = 10000): string
    {
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = (string) preg_replace("/[ \t\x{00A0}]+/u", ' ', $text);
        $text = (string) preg_replace("/\n{3,}/", "\n\n", str_replace("\r", '', $text));

        return mb_substr(trim($text), 0, $max);
    }
}
