<?php

namespace App\Enums;

/**
 * AI providers the platform can talk to (Admin → Settings → Integrations).
 */
enum AiProviderName: string
{
    case OpenAi = 'openai';
    case Anthropic = 'anthropic';

    public function label(): string
    {
        return match ($this) {
            self::OpenAi => 'OpenAI',
            self::Anthropic => 'Anthropic',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $provider): array => [$provider->value => $provider->label()])->all();
    }
}
