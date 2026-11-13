<?php

namespace App\Services\Mcp;

use Illuminate\Support\Str;

/**
 * Makes MCP call arguments safe to keep in the tool-call log: secrets and personal data are redacted, long text is
 * truncated and deep or large structures are cut.
 */
class McpArgumentSanitizer
{
    private const SECRET_KEYS = '/token|password|secret|authorization|api[_-]?key|cookie|bearer/i';

    /**
     * Keys that hold personal data anywhere.
     *
     * @var list<string>
     */
    private const PII_KEYS = ['email', 'phone', 'ip', 'ip_address'];

    /**
     * Keys that hold personal data in lead tools (a person's name, company and free text about them).
     *
     * @var list<string>
     */
    private const LEAD_PII_KEYS = ['name', 'company', 'description', 'goals', 'features', 'existing_url'];

    private const MAX_STRING = 200;

    private const MAX_ITEMS = 30;

    private const MAX_DEPTH = 4;

    /**
     * @param  array<array-key, mixed>  $arguments
     * @return array<array-key, mixed>
     */
    public function sanitize(array $arguments, string $tool): array
    {
        $piiKeys = str_contains($tool, 'lead') ? [...self::PII_KEYS, ...self::LEAD_PII_KEYS] : self::PII_KEYS;

        return $this->walk($arguments, $piiKeys, 1);
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @param  list<string>  $piiKeys
     * @return array<array-key, mixed>
     */
    private function walk(array $values, array $piiKeys, int $depth): array
    {
        $clean = [];
        $count = 0;

        foreach ($values as $key => $value) {
            if (++$count > self::MAX_ITEMS) {
                $clean['…'] = (count($values) - self::MAX_ITEMS).' more';
                break;
            }

            if (is_string($key) && (preg_match(self::SECRET_KEYS, $key) || in_array(mb_strtolower($key), $piiKeys, true))) {
                $clean[$key] = '[redacted]';

                continue;
            }

            $clean[$key] = match (true) {
                is_array($value) => $depth >= self::MAX_DEPTH ? '[…]' : $this->walk($value, $piiKeys, $depth + 1),
                is_string($value) => $this->scrub($value),
                is_scalar($value), $value === null => $value,
                default => '['.get_debug_type($value).']',
            };
        }

        return $clean;
    }

    private function scrub(string $value): string
    {
        // Anything that looks like a bearer token or an email address inside free text is masked too.
        $value = (string) preg_replace('/\b\d+\|[A-Za-z0-9]{20,}\b/', '[redacted]', $value);
        $value = (string) preg_replace('/[^\s@<>()]+@[^\s@<>()]+\.[a-z]{2,}/i', '[email]', $value);

        return Str::limit($value, self::MAX_STRING, '…');
    }
}
