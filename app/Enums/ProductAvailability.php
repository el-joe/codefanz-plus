<?php

namespace App\Enums;

enum ProductAvailability: string
{
    case Available = 'available';
    case Beta = 'beta';
    case ComingSoon = 'coming_soon';

    public function label(): string
    {
        return match ($this) {
            self::Available => __('Available'),
            self::Beta => __('Beta'),
            self::ComingSoon => __('Coming soon'),
        };
    }

    /**
     * Badge tone used by `<x-ui.badge>`.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Available => 'success',
            self::Beta => 'info',
            self::ComingSoon => 'neutral',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $availability): array => [$availability->value => $availability->label()])->all();
    }
}
