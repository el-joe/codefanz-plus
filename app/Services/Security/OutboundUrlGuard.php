<?php

namespace App\Services\Security;

use Closure;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use RuntimeException;

/**
 * SSRF guard for outbound requests to URLs that come from content or admin input (SEO external link check, n8n
 * webhooks). Only http(s) URLs whose host resolves exclusively to public addresses are allowed: loopback, private,
 * link-local (cloud metadata 169.254.169.254), carrier-grade NAT and reserved ranges are refused. Request options
 * pin the connection to the vetted address and re-check every redirect hop.
 *
 * `codefanz.security.allow_private_outbound` (ALLOW_PRIVATE_OUTBOUND) lifts the address check for local
 * development, e.g. n8n on localhost.
 */
class OutboundUrlGuard
{
    private static ?Closure $resolver = null;

    /**
     * Replace DNS resolution (tests). The closure receives a host name and returns its IP addresses.
     *
     * @param  (Closure(string): list<string>)|null  $resolver
     */
    public static function resolveHostsUsing(?Closure $resolver): void
    {
        self::$resolver = $resolver;
    }

    public function isAllowed(string $url): bool
    {
        return $this->reason($url) === null;
    }

    /**
     * Why the URL is refused ("scheme", "host", "unresolvable", "private"), or null when it is allowed.
     */
    public function reason(string $url): ?string
    {
        $parts = parse_url($url);

        if (! is_array($parts) || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
            return 'scheme';
        }

        $host = strtolower(trim((string) ($parts['host'] ?? ''), '[]'));

        if ($host === '') {
            return 'host';
        }

        if (config('codefanz.security.allow_private_outbound')) {
            return null;
        }

        $addresses = $this->addresses($host);

        if ($addresses === []) {
            return 'unresolvable';
        }

        foreach ($addresses as $address) {
            if (! self::isPublicAddress($address)) {
                return 'private';
            }
        }

        return null;
    }

    /**
     * Guzzle options for a request to an allowed URL: the connection is pinned to the vetted address (no DNS
     * rebinding between the check and the request) and redirects are either off or re-checked on every hop.
     *
     * @return array<string, mixed>
     */
    public function requestOptions(string $url, int $maxRedirects = 0): array
    {
        $options = [
            'allow_redirects' => $maxRedirects > 0 ? [
                'max' => $maxRedirects,
                'protocols' => ['http', 'https'],
                'on_redirect' => function (RequestInterface $request, ResponseInterface $response, UriInterface $uri): void {
                    if (! $this->isAllowed((string) $uri)) {
                        throw new RuntimeException('Redirect to a disallowed address.');
                    }
                },
            ] : false,
        ];

        $parts = parse_url($url);
        $host = strtolower(trim((string) ($parts['host'] ?? ''), '[]'));

        if ($host !== '' && filter_var($host, FILTER_VALIDATE_IP) === false && ! config('codefanz.security.allow_private_outbound') && defined('CURLOPT_RESOLVE')) {
            $address = $this->addresses($host)[0] ?? null;
            $port = $parts['port'] ?? (strtolower($parts['scheme'] ?? '') === 'https' ? 443 : 80);

            if ($address !== null) {
                $options['curl'] = [CURLOPT_RESOLVE => [sprintf('%s:%d:%s', $host, $port, str_contains($address, ':') ? '['.$address.']' : $address)]];
            }
        }

        return $options;
    }

    public static function isPublicAddress(string $address): bool
    {
        $address = strtolower($address);

        // IPv4-mapped / compatible IPv6 (::ffff:127.0.0.1) is judged by its IPv4 part.
        if (preg_match('/^::(ffff:)?(\d{1,3}(?:\.\d{1,3}){3})$/', $address, $matches) === 1) {
            $address = $matches[2];
        }

        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }

        // Carrier-grade NAT (100.64.0.0/10) is not covered by the PHP flags.
        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            $long = ip2long($address);

            return ! ($long >= ip2long('100.64.0.0') && $long <= ip2long('100.127.255.255'));
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private function addresses(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        if ($host === 'localhost' || str_ends_with($host, '.localhost')) {
            return ['127.0.0.1'];
        }

        if (self::$resolver !== null) {
            return array_values((self::$resolver)($host));
        }

        $addresses = gethostbynamel($host) ?: [];

        foreach (@dns_get_record($host, DNS_AAAA) ?: [] as $record) {
            if (isset($record['ipv6'])) {
                $addresses[] = $record['ipv6'];
            }
        }

        return array_values(array_unique($addresses));
    }
}
