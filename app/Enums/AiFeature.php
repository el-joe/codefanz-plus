<?php

namespace App\Enums;

/**
 * Features that call an AI provider. Each one is metered separately and paused by its own kill switch.
 */
enum AiFeature: string
{
    case ProjectAssistant = 'project_assistant';
    case ContentStudio = 'content_studio';
    case Radar = 'radar';
    case SeoAssistant = 'seo_assistant';

    public function label(): string
    {
        return match ($this) {
            self::ProjectAssistant => __('AI Project Assistant'),
            self::ContentStudio => __('AI Content Studio'),
            self::Radar => __('Freelance Radar'),
            self::SeoAssistant => __('AI SEO suggestions'),
        };
    }

    public function killSwitch(): KillSwitchKey
    {
        return match ($this) {
            self::ProjectAssistant => KillSwitchKey::AiAssistant,
            self::ContentStudio, self::Radar, self::SeoAssistant => KillSwitchKey::AiAutomation,
        };
    }
}
