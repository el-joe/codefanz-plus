<?php

namespace App\Enums;

/**
 * Outcome of one MCP tool call as recorded in mcp_tool_calls.
 */
enum McpCallResult: string
{
    case Ok = 'ok';
    case Denied = 'denied';
    case Invalid = 'invalid';
    case Conflict = 'conflict';
    case RateLimited = 'rate_limited';
    case Error = 'error';

    public function label(): string
    {
        return match ($this) {
            self::Ok => __('OK'),
            self::Denied => __('Denied'),
            self::Invalid => __('Invalid input'),
            self::Conflict => __('Conflict'),
            self::RateLimited => __('Rate limited'),
            self::Error => __('Error'),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Ok => 'success',
            self::Invalid, self::RateLimited => 'warning',
            self::Denied, self::Conflict, self::Error => 'danger',
        };
    }

    public static function fromStatus(int $status): self
    {
        return match (true) {
            $status < 400 => self::Ok,
            $status === 401, $status === 403, $status === 503 => self::Denied,
            $status === 409 => self::Conflict,
            $status === 422, $status === 404 => self::Invalid,
            $status === 429 => self::RateLimited,
            default => self::Error,
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $result): array => [$result->value => $result->label()])->all();
    }
}
