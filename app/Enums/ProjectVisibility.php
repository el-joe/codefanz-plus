<?php

namespace App\Enums;

/**
 * Where a published portfolio project appears. Unlisted projects are reachable by direct link only
 * (not listed, not indexed, and they do not mark technologies as verified).
 */
enum ProjectVisibility: string
{
    case Public = 'public';
    case Unlisted = 'unlisted';

    public function label(): string
    {
        return match ($this) {
            self::Public => __('Public'),
            self::Unlisted => __('Unlisted (direct link only)'),
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
