<?php

namespace App\Enums;

enum RadarBudgetType: string
{
    case Fixed = 'fixed';
    case Hourly = 'hourly';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => __('Fixed price'),
            self::Hourly => __('Hourly'),
            self::Unknown => __('Not stated'),
        };
    }
}
