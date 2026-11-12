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

    public function label(): string
    {
        return match ($this) {
            self::NotConfigured => __('Not configured'),
            self::Disabled => __('Paused by an emergency control'),
            self::BudgetExceeded => __('Monthly budget reached'),
            self::Failed => __('Provider error'),
        };
    }
}
