<?php

namespace App\Enums;

enum MediaStatus: string
{
    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';

    public function tone(): string
    {
        return match ($this) {
            self::Processing => 'info',
            self::Ready => 'success',
            self::Failed => 'danger',
        };
    }
}
