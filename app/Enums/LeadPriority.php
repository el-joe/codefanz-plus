<?php

namespace App\Enums;

enum LeadPriority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';

    public function label(): string
    {
        return match ($this) {
            self::Low => __('Low'),
            self::Normal => __('Normal'),
            self::High => __('High'),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Low => 'neutral',
            self::Normal => 'info',
            self::High => 'danger',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $priority): array => [$priority->value => $priority->label()])->all();
    }
}
