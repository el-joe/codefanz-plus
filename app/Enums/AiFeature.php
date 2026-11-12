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

    public function label(): string
    {
        return match ($this) {
            self::ProjectAssistant => __('AI Project Assistant'),
            self::ContentStudio => __('AI Content Studio'),
            self::Radar => __('Freelance Radar'),
        };
    }

    public function killSwitch(): KillSwitchKey
    {
        return match ($this) {
            self::ProjectAssistant => KillSwitchKey::AiAssistant,
            self::ContentStudio, self::Radar => KillSwitchKey::AiAutomation,
        };
    }
}
