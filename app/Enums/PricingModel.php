<?php

namespace App\Enums;

/**
 * Licensing models a software product may be offered under. Prices are never stored or shown: every quote is preliminary.
 */
enum PricingModel: string
{
    case Subscription = 'subscription';
    case OneTimeLicense = 'one_time_license';
    case CustomQuote = 'custom_quote';

    public function label(): string
    {
        return match ($this) {
            self::Subscription => __('Cloud subscription'),
            self::OneTimeLicense => __('One-time license'),
            self::CustomQuote => __('Custom quote'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Subscription => __('Hosted and maintained by CodeFanz. Billed monthly or yearly.'),
            self::OneTimeLicense => __('Install on your own servers with a perpetual license and optional annual support.'),
            self::CustomQuote => __('Custom modules, integrations and service levels for larger operations.'),
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $model): array => [$model->value => $model->label()])->all();
    }
}
