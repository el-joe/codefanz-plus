<?php

namespace App\Enums;

/**
 * Publication state for catalog content (services, industries and later portfolio, products, testimonials).
 */
enum ContentStatus: string
{
    case Draft = 'draft';
    case Published = 'published';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Published => __('Published'),
        };
    }
}
