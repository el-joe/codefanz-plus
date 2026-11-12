<?php

namespace App\Enums;

/**
 * Kind of delivered work shown as a badge on portfolio projects.
 */
enum ProjectType: string
{
    case Web = 'web';
    case Mobile = 'mobile';
    case Saas = 'saas';
    case Ai = 'ai';
    case Ecommerce = 'ecommerce';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Web => __('Web App'),
            self::Mobile => __('Mobile App'),
            self::Saas => __('SaaS Platform'),
            self::Ai => __('AI Project'),
            self::Ecommerce => __('E-commerce'),
            self::Other => __('Other'),
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $type): array => [$type->value => $type->label()])->all();
    }
}
