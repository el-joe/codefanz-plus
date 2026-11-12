<?php

namespace App\Enums;

enum ContactMethod: string
{
    case Email = 'email';
    case Whatsapp = 'whatsapp';
    case Phone = 'phone';

    public function label(): string
    {
        return match ($this) {
            self::Email => __('Email'),
            self::Whatsapp => __('WhatsApp'),
            self::Phone => __('Phone'),
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $method): array => [$method->value => $method->label()])->all();
    }
}
