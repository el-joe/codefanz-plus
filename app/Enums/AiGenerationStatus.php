<?php

namespace App\Enums;

enum AiGenerationStatus: string
{
    case Generated = 'generated';
    case Failed = 'failed';
    case DraftCreated = 'draft_created';
    case Proposed = 'proposed';
    case Saved = 'saved';
    case Discarded = 'discarded';

    public function label(): string
    {
        return match ($this) {
            self::Generated => __('Awaiting your review'),
            self::Failed => __('Failed'),
            self::DraftCreated => __('Blog draft created'),
            self::Proposed => __('In approval queue'),
            self::Saved => __('Private draft'),
            self::Discarded => __('Discarded'),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Generated => 'info',
            self::Failed, self::Discarded => 'danger',
            self::DraftCreated, self::Proposed => 'warning',
            self::Saved => 'neutral',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Generated, self::Saved], true);
    }
}
