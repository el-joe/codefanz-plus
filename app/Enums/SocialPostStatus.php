<?php

namespace App\Enums;

/**
 * Workflow of a social post: draft → in review → (changes requested ↺) approved → scheduled → publishing →
 * published / failed / exported manually. Nothing reaches the queue without an approval, and AI-origin posts need a
 * reviewer other than the person who generated them.
 */
enum SocialPostStatus: string
{
    case Draft = 'draft';
    case InReview = 'in_review';
    case ChangesRequested = 'changes_requested';
    case Approved = 'approved';
    case Scheduled = 'scheduled';
    case Publishing = 'publishing';
    case Published = 'published';
    case Failed = 'failed';
    case ExportedManually = 'exported_manually';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::InReview => __('In review'),
            self::ChangesRequested => __('Changes requested'),
            self::Approved => __('Approved'),
            self::Scheduled => __('Scheduled'),
            self::Publishing => __('Publishing'),
            self::Published => __('Published'),
            self::Failed => __('Failed'),
            self::ExportedManually => __('Posted manually'),
            self::Cancelled => __('Cancelled'),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft, self::Cancelled => 'neutral',
            self::InReview => 'info',
            self::ChangesRequested => 'warning',
            self::Approved => 'accent',
            self::Scheduled, self::Publishing => 'brand',
            self::Published, self::ExportedManually => 'success',
            self::Failed => 'danger',
        };
    }

    /**
     * States whose content can still be edited (an approved or scheduled post goes back to draft when edited).
     */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::ChangesRequested, self::Approved, self::Scheduled], true);
    }

    public function canBeSubmitted(): bool
    {
        return in_array($this, [self::Draft, self::ChangesRequested], true);
    }

    /**
     * Approved posts that may be (re)scheduled, also from the calendar.
     */
    public function canBeScheduled(): bool
    {
        return in_array($this, [self::Approved, self::Scheduled], true);
    }

    /**
     * Statuses offered in the list and calendar filters.
     *
     * @return list<self>
     */
    public static function filterable(): array
    {
        return self::cases();
    }
}
