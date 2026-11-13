<?php

namespace App\Enums;

/**
 * Notification level by rule score (thresholds configurable): immediate and normal alerts are sent at once, digest
 * projects wait for the daily digest, anything lower is saved without a notification.
 */
enum RadarAlertLevel: string
{
    case Immediate = 'immediate';
    case Normal = 'normal';
    case Digest = 'digest';

    public function label(): string
    {
        return match ($this) {
            self::Immediate => __('Immediate alert'),
            self::Normal => __('Normal alert'),
            self::Digest => __('Daily digest'),
        };
    }
}
