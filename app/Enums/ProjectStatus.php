<?php

namespace App\Enums;

/**
 * Delivery project lifecycle. Completed and cancelled projects are closed; every other status is active.
 */
enum ProjectStatus: string
{
    case Planning = 'planning';
    case InProgress = 'in_progress';
    case OnHold = 'on_hold';
    case Review = 'review';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Planning => __('Planning'),
            self::InProgress => __('In progress'),
            self::OnHold => __('On hold'),
            self::Review => __('Under review'),
            self::Completed => __('Completed'),
            self::Cancelled => __('Cancelled'),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Planning => 'neutral',
            self::InProgress => 'info',
            self::OnHold => 'warning',
            self::Review => 'accent',
            self::Completed => 'success',
            self::Cancelled => 'danger',
        };
    }

    public function isActive(): bool
    {
        return ! in_array($this, [self::Completed, self::Cancelled], true);
    }

    /**
     * @return list<self>
     */
    public static function active(): array
    {
        return array_values(array_filter(self::cases(), fn (self $status): bool => $status->isActive()));
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $status): array => [$status->value => $status->label()])->all();
    }
}
