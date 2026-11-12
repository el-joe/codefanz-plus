<?php

namespace App\Enums;

/**
 * When the visitor would like the project delivered.
 */
enum ProjectTimeline: string
{
    case Asap = 'asap';
    case OneToThreeMonths = '1_3_months';
    case ThreeToSixMonths = '3_6_months';
    case SixPlusMonths = '6_plus_months';
    case Flexible = 'flexible';

    public function label(): string
    {
        return match ($this) {
            self::Asap => __('As soon as possible'),
            self::OneToThreeMonths => __('1–3 months'),
            self::ThreeToSixMonths => __('3–6 months'),
            self::SixPlusMonths => __('6+ months'),
            self::Flexible => __('Flexible'),
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $timeline): array => [$timeline->value => $timeline->label()])->all();
    }
}
