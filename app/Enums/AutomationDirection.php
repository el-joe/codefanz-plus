<?php

namespace App\Enums;

enum AutomationDirection: string
{
    case Outbound = 'outbound';
    case Inbound = 'inbound';

    public function label(): string
    {
        return match ($this) {
            self::Outbound => __('Outbound'),
            self::Inbound => __('Inbound'),
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $direction): array => [$direction->value => $direction->label()])->all();
    }
}
