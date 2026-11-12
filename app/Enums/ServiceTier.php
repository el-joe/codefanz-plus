<?php

namespace App\Enums;

enum ServiceTier: string
{
    case Core = 'core';
    case Specialist = 'specialist';

    public function label(): string
    {
        return match ($this) {
            self::Core => __('Core service'),
            self::Specialist => __('Specialist service'),
        };
    }
}
