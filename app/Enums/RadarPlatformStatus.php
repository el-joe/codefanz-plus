<?php

namespace App\Enums;

enum RadarPlatformStatus: string
{
    case Active = 'active';
    case NeedsCredentials = 'needs_credentials';
    case Unavailable = 'unavailable';
    case Error = 'error';
    case Paused = 'paused';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('Active'),
            self::NeedsCredentials => __('Requires credentials'),
            self::Unavailable => __('Unavailable'),
            self::Error => __('Error'),
            self::Paused => __('Paused'),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::NeedsCredentials => 'warning',
            self::Unavailable, self::Paused => 'neutral',
            self::Error => 'danger',
        };
    }
}
