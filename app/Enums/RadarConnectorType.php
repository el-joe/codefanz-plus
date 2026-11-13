<?php

namespace App\Enums;

/**
 * How a radar platform's projects are collected. Only compliant methods exist: official APIs and alert emails
 * the platform sends to us (forwarded by n8n / the mail provider). There is no scraping connector.
 */
enum RadarConnectorType: string
{
    case Api = 'api';
    case Email = 'email';
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::Api => __('Official API'),
            self::Email => __('Email alerts'),
            self::None => __('No permitted integration'),
        };
    }
}
