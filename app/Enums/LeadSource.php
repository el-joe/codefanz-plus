<?php

namespace App\Enums;

/**
 * Where a lead came from.
 */
enum LeadSource: string
{
    case QuoteForm = 'quote_form';
    case ContactForm = 'contact_form';
    case AiAssistant = 'ai_assistant';
    case Manual = 'manual';
    case Mcp = 'mcp';
    case Radar = 'radar';

    public function label(): string
    {
        return match ($this) {
            self::QuoteForm => __('Quote form'),
            self::ContactForm => __('Contact form'),
            self::AiAssistant => __('AI assistant'),
            self::Manual => __('Manual'),
            self::Mcp => __('MCP'),
            self::Radar => __('Freelance Radar'),
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $source): array => [$source->value => $source->label()])->all();
    }
}
