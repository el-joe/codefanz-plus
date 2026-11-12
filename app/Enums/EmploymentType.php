<?php

namespace App\Enums;

/**
 * Employment type of an open position, mapped to schema.org JobPosting values.
 */
enum EmploymentType: string
{
    case FullTime = 'full_time';
    case PartTime = 'part_time';
    case Contract = 'contract';
    case Internship = 'internship';
    case Temporary = 'temporary';

    public function label(): string
    {
        return match ($this) {
            self::FullTime => __('Full-time'),
            self::PartTime => __('Part-time'),
            self::Contract => __('Contract'),
            self::Internship => __('Internship'),
            self::Temporary => __('Temporary'),
        };
    }

    public function schemaValue(): string
    {
        return strtoupper($this->value);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $type): array => [$type->value => $type->label()])->all();
    }
}
