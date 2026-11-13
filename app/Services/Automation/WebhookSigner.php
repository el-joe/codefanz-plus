<?php

namespace App\Services\Automation;

/**
 * HMAC-SHA256 over "{timestamp}.{raw body}" with the workflow's own secret, sent as "sha256=<hex>". The same scheme
 * signs outbound deliveries and verifies inbound requests (constant-time comparison, timestamp tolerance).
 */
class WebhookSigner
{
    public const HEADER_SIGNATURE = 'X-CodeFanz-Signature';

    public const HEADER_TIMESTAMP = 'X-CodeFanz-Timestamp';

    public const HEADER_DELIVERY = 'X-CodeFanz-Delivery';

    public const HEADER_EVENT = 'X-CodeFanz-Event';

    public function sign(string $secret, int $timestamp, string $body): string
    {
        return 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, $secret);
    }

    public function verify(string $secret, string $timestamp, string $body, string $signature): bool
    {
        if (! ctype_digit($timestamp)) {
            return false;
        }

        return hash_equals($this->sign($secret, (int) $timestamp, $body), trim($signature));
    }

    public function isFresh(string $timestamp): bool
    {
        return ctype_digit($timestamp) && abs(now()->getTimestamp() - (int) $timestamp) <= (int) config('codefanz.automation.signature_tolerance_seconds', 300);
    }
}
