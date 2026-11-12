<?php

namespace App\Services\Ai;

use App\Enums\GuardrailFlagType;
use App\Models\BrandProfile;

/**
 * Checks AI output (and staff edits of it) for wording the brand must not publish: banned claims, numbers that do not
 * appear in what the author supplied (likely fabricated statistics), prices, and links to domains other than this
 * site and the brand profile's approved list.
 */
class ContentGuardrails
{
    /**
     * Claims rejected even when the brand profile is empty.
     */
    private const BUILT_IN_CLAIMS = ['guaranteed', 'guarantee', '100% satisfaction', 'risk-free', 'نضمن', 'مضمون'];

    private const CURRENCY = '\$|€|£|USD|EUR|GBP|EGP|SAR|AED|KWD|QAR|ج\.م|جنيه|ريال|درهم|دولار|يورو';

    /**
     * @param  array<string, array<string, string>>  $output  {locale: {field: text}}
     * @param  string  $knownText  everything the author supplied (inputs and current content)
     * @return list<array{type: string, locale: string, field: string, excerpt: string}>
     */
    public function check(array $output, string $knownText, BrandProfile $brand): array
    {
        $flags = [];
        $knownNumbers = $this->numbers($knownText);
        $claims = array_values(array_unique([...self::BUILT_IN_CLAIMS, ...$brand->bannedClaims()]));
        $allowedHosts = $this->allowedHosts($brand);

        foreach ($output as $locale => $fields) {
            foreach ($fields as $field => $text) {
                $text = (string) $text;

                foreach ($claims as $claim) {
                    if (mb_stripos($text, $claim) !== false) {
                        $flags[] = $this->flag(GuardrailFlagType::BannedClaim, $locale, $field, $claim);
                    }
                }

                foreach ($this->prices($text) as $price) {
                    $flags[] = $this->flag(GuardrailFlagType::Price, $locale, $field, $price);
                }

                foreach (array_diff($this->numbers($text), $knownNumbers) as $number) {
                    $flags[] = $this->flag(GuardrailFlagType::UnverifiedNumber, $locale, $field, $number);
                }

                foreach ($this->links($text) as $url => $host) {
                    if (! $this->hostAllowed($host, $allowedHosts)) {
                        $flags[] = $this->flag(GuardrailFlagType::UnknownLink, $locale, $field, $url);
                    }
                }
            }
        }

        return array_values(array_unique($flags, SORT_REGULAR));
    }

    /**
     * Numbers worth checking: anything with a percent sign, decimals, or two or more digits (small counts such as
     * "3 steps" are ordinary writing). Arabic-Indic digits are normalised first.
     *
     * @return list<string>
     */
    public function numbers(string $text): array
    {
        $text = strtr($text, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9', '٫' => '.', '٬' => ',', '٪' => '%']);
        // Links are checked separately; their path digits are not claims.
        $text = (string) preg_replace('~(https?://|www\.)\S+~iu', ' ', $text);

        preg_match_all('/(?<![\p{L}\d])\d[\d,]*(?:\.\d+)?\s?%?/u', $text, $matches);

        $numbers = array_map(fn (string $number): string => str_replace([',', ' '], '', $number), $matches[0]);

        return array_values(array_unique(array_filter(
            $numbers,
            fn (string $number): bool => str_ends_with($number, '%') || str_contains($number, '.') || strlen($number) >= 2,
        )));
    }

    /**
     * @return list<string>
     */
    private function prices(string $text): array
    {
        $currency = self::CURRENCY;
        preg_match_all("/(?:(?:{$currency})\s?\d[\d,.]*|\d[\d,.]*\s?(?:{$currency}))/iu", $text, $matches);

        return array_values(array_unique(array_map('trim', $matches[0])));
    }

    /**
     * @return array<string, string> url => lowercase host
     */
    private function links(string $text): array
    {
        preg_match_all('~\b(?:https?://|www\.)[^\s<>()\[\]"\']+~iu', $text, $matches);
        $links = [];

        foreach ($matches[0] as $url) {
            $url = rtrim($url, '.,;:!?');
            $host = parse_url(str_starts_with(mb_strtolower($url), 'www.') ? 'https://'.$url : $url, PHP_URL_HOST);

            if (is_string($host) && $host !== '') {
                $links[$url] = mb_strtolower($host);
            }
        }

        return $links;
    }

    /**
     * @return list<string>
     */
    private function allowedHosts(BrandProfile $brand): array
    {
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        return array_values(array_filter([is_string($appHost) ? mb_strtolower($appHost) : null, ...$brand->allowedDomains()]));
    }

    /**
     * @param  list<string>  $allowed
     */
    private function hostAllowed(string $host, array $allowed): bool
    {
        $host = preg_replace('/^www\./', '', $host);

        foreach ($allowed as $domain) {
            $domain = (string) preg_replace('/^www\./', '', $domain);

            if ($host === $domain || str_ends_with((string) $host, '.'.$domain)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{type: string, locale: string, field: string, excerpt: string}
     */
    private function flag(GuardrailFlagType $type, string $locale, string $field, string $excerpt): array
    {
        return ['type' => $type->value, 'locale' => $locale, 'field' => $field, 'excerpt' => mb_substr($excerpt, 0, 120)];
    }
}
