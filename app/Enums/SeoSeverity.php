<?php

namespace App\Enums;

enum SeoSeverity: string
{
    case Error = 'error';
    case Warning = 'warning';
    case Notice = 'notice';

    public function label(): string
    {
        return match ($this) {
            self::Error => __('High'),
            self::Warning => __('Medium'),
            self::Notice => __('Low'),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Error => 'danger',
            self::Warning => 'warning',
            self::Notice => 'info',
        };
    }

    /**
     * Points a page loses for each issue of this severity (page score starts at 100, floor 0).
     */
    public function penalty(): int
    {
        return match ($this) {
            self::Error => 25,
            self::Warning => 10,
            self::Notice => 2,
        };
    }
}
