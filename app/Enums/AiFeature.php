<?php

namespace App\Enums;

/**
 * Features that call an AI provider. Each one is metered separately, can have its own monthly cap and is paused by
 * its own kill switch (plus the "all AI automation" master switch for staff-side features).
 */
enum AiFeature: string
{
    case ProjectAssistant = 'project_assistant';
    case ContentStudio = 'content_studio';
    case Radar = 'radar';
    case SeoAssistant = 'seo_assistant';
    case Social = 'social';

    public function label(): string
    {
        return match ($this) {
            self::ProjectAssistant => __('AI Project Assistant'),
            self::ContentStudio => __('AI Content Studio'),
            self::Radar => __('Freelance Radar'),
            self::SeoAssistant => __('AI SEO suggestions'),
            self::Social => __('AI social copy'),
        };
    }

    /**
     * The feature's own kill switch.
     */
    public function killSwitch(): KillSwitchKey
    {
        return match ($this) {
            self::ProjectAssistant => KillSwitchKey::AiAssistant,
            self::ContentStudio => KillSwitchKey::AiContentStudio,
            self::Radar => KillSwitchKey::AiRadar,
            self::SeoAssistant => KillSwitchKey::AiSeo,
            self::Social => KillSwitchKey::AiSocial,
        };
    }

    /**
     * Every switch that pauses this feature. The public assistant has its own switch only, so pausing staff
     * automation never takes the visitor-facing questionnaire down.
     *
     * @return list<KillSwitchKey>
     */
    public function killSwitches(): array
    {
        return $this === self::ProjectAssistant
            ? [KillSwitchKey::AiAssistant]
            : [KillSwitchKey::AiAutomation, $this->killSwitch()];
    }
}
