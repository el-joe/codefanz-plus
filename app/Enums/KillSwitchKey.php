<?php

namespace App\Enums;

enum KillSwitchKey: string
{
    case AiAutomation = 'ai_automation';
    case SocialPublishing = 'social_publishing';
    case McpWrites = 'mcp_writes';
    case McpAccess = 'mcp_access';
    case AiAssistant = 'ai_assistant';
    case AiContentStudio = 'ai_content_studio';
    case AiSeo = 'ai_seo';
    case AiSocial = 'ai_social';
    case AiRadar = 'ai_radar';

    public function label(): string
    {
        return match ($this) {
            self::AiAutomation => __('All AI automation'),
            self::SocialPublishing => __('Social publishing'),
            self::McpWrites => __('MCP write operations'),
            self::McpAccess => __('All MCP access'),
            self::AiAssistant => __('AI Project Assistant'),
            self::AiContentStudio => __('AI Content Studio'),
            self::AiSeo => __('AI SEO suggestions'),
            self::AiSocial => __('AI social copy'),
            self::AiRadar => __('Freelance Radar AI'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::AiAutomation => __('Master switch: stops every AI generation and blocks applying AI change requests.'),
            self::SocialPublishing => __('Stops scheduled and manual social posts from being published.'),
            self::McpWrites => __('MCP draft and execute tools return an error; read-only tools keep working.'),
            self::McpAccess => __('Every MCP call is refused, including read-only tools.'),
            self::AiAssistant => __('The public assistant switches to its guided questionnaire.'),
            self::AiContentStudio => __('No new generations in the Content Studio.'),
            self::AiSeo => __('No AI SEO suggestions or metadata generations.'),
            self::AiSocial => __('No AI-written social captions.'),
            self::AiRadar => __('No AI scoring of freelance projects.'),
        };
    }
}
