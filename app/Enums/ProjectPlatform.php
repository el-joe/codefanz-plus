<?php

namespace App\Enums;

/**
 * Platforms a requested project must run on.
 */
enum ProjectPlatform: string
{
    case Web = 'web';
    case Android = 'android';
    case Ios = 'ios';
    case Desktop = 'desktop';

    public function label(): string
    {
        return match ($this) {
            self::Web => __('Web'),
            self::Android => __('Android'),
            self::Ios => __('iOS'),
            self::Desktop => __('Desktop'),
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $platform): array => [$platform->value => $platform->label()])->all();
    }
}
