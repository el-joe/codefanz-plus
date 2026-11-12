<?php

namespace App\Services\Seo;

/**
 * Normalizes redirect sources and targets so lookups are exact: a decoded site path with one leading slash, no
 * trailing slash (except "/"), no query string or fragment. Targets may also be absolute https URLs.
 */
class RedirectPaths
{
    /**
     * A site path, or null when the value is not one (other host, traversal, empty).
     */
    public static function source(string $value): ?string
    {
        $value = trim($value);

        if (preg_match('#^https?://#i', $value) === 1) {
            if (! self::isOwnHost((string) parse_url($value, PHP_URL_HOST))) {
                return null;
            }

            $value = (string) parse_url($value, PHP_URL_PATH);
        }

        $value = (string) strtok($value, '?#');

        return self::path($value);
    }

    /**
     * A site path (query string kept) or an absolute https URL to another host; null when invalid.
     */
    public static function target(string $value): ?string
    {
        $value = trim($value);

        if (preg_match('#^https?://#i', $value) === 1) {
            $host = (string) parse_url($value, PHP_URL_HOST);

            if (self::isOwnHost($host)) {
                $query = parse_url($value, PHP_URL_QUERY);
                $path = self::path((string) parse_url($value, PHP_URL_PATH));

                return $path === null ? null : $path.(is_string($query) && $query !== '' ? '?'.$query : '');
            }

            return str_starts_with(strtolower($value), 'https://') && $host !== '' && filter_var($value, FILTER_VALIDATE_URL) !== false
                ? $value
                : null;
        }

        // Protocol-relative URLs and other schemes (javascript:, data:, mailto: …) are never valid targets.
        if (str_starts_with($value, '//') || preg_match('#^[a-z][a-z0-9+.-]*:#i', $value) === 1) {
            return null;
        }

        $query = str_contains($value, '?') ? substr($value, strpos($value, '?') + 1) : '';
        $path = self::path((string) strtok($value, '?#'));

        return $path === null ? null : $path.($query !== '' ? '?'.strtok($query, '#') : '');
    }

    public static function isExternal(?string $target): bool
    {
        return $target !== null && preg_match('#^https?://#i', $target) === 1;
    }

    /**
     * The path part of a target used for chain detection (null for external URLs).
     */
    public static function targetPath(?string $target): ?string
    {
        if ($target === null || self::isExternal($target)) {
            return null;
        }

        return (string) strtok($target, '?');
    }

    /**
     * A target ready for a Location header: internal paths are percent-encoded per segment.
     */
    public static function encode(string $target): string
    {
        if (self::isExternal($target)) {
            return $target;
        }

        $query = str_contains($target, '?') ? '?'.substr($target, strpos($target, '?') + 1) : '';
        $path = (string) strtok($target, '?');

        return implode('/', array_map('rawurlencode', explode('/', $path))).$query;
    }

    private static function path(string $value): ?string
    {
        $value = rawurldecode($value);

        if ($value === '' || str_contains($value, '..') || preg_match('/[\x00-\x1F\x7F\\\\]/', $value) === 1) {
            return $value === '' ? '/' : null;
        }

        $value = '/'.ltrim((string) preg_replace('#/+#', '/', $value), '/');
        $value = $value === '/' ? '/' : rtrim($value, '/');

        return mb_strlen($value) <= 500 ? $value : null;
    }

    private static function isOwnHost(string $host): bool
    {
        return $host !== '' && strcasecmp($host, (string) parse_url((string) config('app.url'), PHP_URL_HOST)) === 0
            || (app()->bound('request') && strcasecmp($host, request()->getHost()) === 0);
    }
}
