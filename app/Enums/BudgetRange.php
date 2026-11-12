<?php

namespace App\Enums;

/**
 * Budget bands offered on the quote form (US dollars, indicative only).
 */
enum BudgetRange: string
{
    case Under5k = 'under_5k';
    case From5kTo10k = '5k_10k';
    case From10kTo25k = '10k_25k';
    case From25kTo50k = '25k_50k';
    case From50kTo100k = '50k_100k';
    case Over100k = 'over_100k';
    case NotSure = 'not_sure';

    public function label(): string
    {
        return match ($this) {
            self::Under5k => __('Under $5k'),
            self::From5kTo10k => __('$5k – $10k'),
            self::From10kTo25k => __('$10k – $25k'),
            self::From25kTo50k => __('$25k – $50k'),
            self::From50kTo100k => __('$50k – $100k'),
            self::Over100k => __('Over $100k'),
            self::NotSure => __('Not sure yet'),
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $range): array => [$range->value => $range->label()])->all();
    }
}
