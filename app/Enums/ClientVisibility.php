<?php

namespace App\Enums;

/**
 * Whether a project note or document is for the team only or also shared with the client in the portal.
 */
enum ClientVisibility: string
{
    case Internal = 'internal';
    case Client = 'client';

    public function label(): string
    {
        return match ($this) {
            self::Internal => __('Internal only'),
            self::Client => __('Visible to client'),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Internal => 'danger',
            self::Client => 'success',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $visibility): array => [$visibility->value => $visibility->label()])->all();
    }
}
