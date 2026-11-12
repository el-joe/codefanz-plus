<?php

namespace App\Enums;

/**
 * Why an AI call did not happen (or failed). The public assistant switches to the guided questionnaire on any of these.
 */
enum AiUnavailableReason: string
{
    case NotConfigured = 'not_configured';
    case Disabled = 'disabled';
    case BudgetExceeded = 'budget_exceeded';
    case Failed = 'failed';
    case UserLimitReached = 'user_limit';
    case FeatureLimitReached = 'feature_limit';

    public function label(): string
    {
        return match ($this) {
            self::NotConfigured => __('Not configured'),
            self::Disabled => __('Paused by an emergency control'),
            self::BudgetExceeded => __('Monthly budget reached'),
            self::Failed => __('Provider error'),
            self::UserLimitReached => __('You have reached your personal AI usage limit. It resets automatically; an administrator can raise it in Settings → AI limits.'),
            self::FeatureLimitReached => __('This feature has reached its monthly AI usage limit. An administrator can raise it in Settings → AI limits.'),
        };
    }
}
