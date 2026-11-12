<?php

namespace App\Enums;

/**
 * What a menu item points to.
 */
enum MenuLinkType: string
{
    case Route = 'route';
    case Linkable = 'linkable';
    case Url = 'url';

    public function label(): string
    {
        return match ($this) {
            self::Route => __('Site section'),
            self::Linkable => __('Content item'),
            self::Url => __('External link'),
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $type): array => [$type->value => $type->label()])->all();
    }
}
