<?php

namespace App\Enums;

enum AiUsageStatus: string
{
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Succeeded => __('Succeeded'),
            self::Failed => __('Failed'),
        };
    }
}
