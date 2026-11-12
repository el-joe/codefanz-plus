<?php

namespace App\Enums;

/**
 * A portal user's role inside one customer account.
 */
enum CustomerUserRole: string
{
    case Owner = 'owner';
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::Owner => __('Account owner'),
            self::Member => __('Team member'),
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $role): array => [$role->value => $role->label()])->all();
    }
}
