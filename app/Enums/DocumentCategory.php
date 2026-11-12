<?php

namespace App\Enums;

enum DocumentCategory: string
{
    case Contract = 'contract';
    case Design = 'design';
    case Technical = 'technical';
    case Report = 'report';
    case Data = 'data';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Contract => __('Contract'),
            self::Design => __('Design'),
            self::Technical => __('Technical'),
            self::Report => __('Report'),
            self::Data => __('Data'),
            self::Other => __('Other'),
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $category): array => [$category->value => $category->label()])->all();
    }
}
