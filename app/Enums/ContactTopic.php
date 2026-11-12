<?php

namespace App\Enums;

/**
 * What a contact-form message is about. Links across the site prefill it with ?topic=…
 */
enum ContactTopic: string
{
    case Quote = 'quote';
    case Demo = 'demo';
    case Careers = 'careers';
    case Support = 'support';
    case General = 'general';

    public function label(): string
    {
        return match ($this) {
            self::Quote => __('New project / quote'),
            self::Demo => __('Product demo'),
            self::Careers => __('Careers'),
            self::Support => __('Support for an existing project'),
            self::General => __('Something else'),
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $topic): array => [$topic->value => $topic->label()])->all();
    }
}
