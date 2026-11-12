<?php

namespace App\Http\Controllers\Concerns;

/**
 * Notifications store the link to the record they are about (`data.url`). Opening one follows that link only
 * as a same-site path under the expected area (/admin/ or /portal/); the host is always dropped.
 */
trait FollowsNotificationLinks
{
    protected function notificationPath(mixed $url, string $pathPrefix): ?string
    {
        $path = is_string($url) ? parse_url($url, PHP_URL_PATH) : null;

        if (! is_string($path) || ! str_starts_with($path, $pathPrefix) || str_contains($path, '..')) {
            return null;
        }

        $query = parse_url($url, PHP_URL_QUERY);

        return $path.(is_string($query) && $query !== '' ? '?'.$query : '');
    }
}
