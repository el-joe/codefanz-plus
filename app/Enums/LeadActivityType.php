<?php

namespace App\Enums;

/**
 * Lead timeline entries. Status changes, assignments and system entries are written by the application only.
 */
enum LeadActivityType: string
{
    case Note = 'note';
    case Call = 'call';
    case Email = 'email';
    case Meeting = 'meeting';
    case StatusChange = 'status_change';
    case Assignment = 'assignment';
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::Note => __('Note'),
            self::Call => __('Call'),
            self::Email => __('Email'),
            self::Meeting => __('Meeting'),
            self::StatusChange => __('Status change'),
            self::Assignment => __('Assignment'),
            self::System => __('System'),
        };
    }

    /**
     * Types staff may log by hand.
     *
     * @return list<self>
     */
    public static function manual(): array
    {
        return [self::Note, self::Call, self::Email, self::Meeting];
    }

    /**
     * @return array<string, string>
     */
    public static function manualOptions(): array
    {
        return collect(self::manual())->mapWithKeys(fn (self $type): array => [$type->value => $type->label()])->all();
    }
}
