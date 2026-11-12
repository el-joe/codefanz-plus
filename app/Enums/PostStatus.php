<?php

namespace App\Enums;

/**
 * Editorial workflow of a blog article: draft → in review → (changes requested ↺) approved → scheduled/published → archived.
 * Only approved articles can be scheduled or published; AI-origin drafts need a reviewer other than their author.
 */
enum PostStatus: string
{
    case Draft = 'draft';
    case InReview = 'in_review';
    case ChangesRequested = 'changes_requested';
    case Approved = 'approved';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::InReview => __('In review'),
            self::ChangesRequested => __('Changes requested'),
            self::Approved => __('Approved'),
            self::Scheduled => __('Scheduled'),
            self::Published => __('Published'),
            self::Archived => __('Archived'),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::InReview => 'info',
            self::ChangesRequested => 'warning',
            self::Approved => 'accent',
            self::Scheduled => 'brand',
            self::Published => 'success',
            self::Archived => 'neutral',
        };
    }

    /**
     * States in which the author may submit the article for review.
     */
    public function canBeSubmitted(): bool
    {
        return in_array($this, [self::Draft, self::ChangesRequested], true);
    }

    /**
     * Content edits on approved or scheduled articles invalidate the approval.
     */
    public function isApprovalLocked(): bool
    {
        return in_array($this, [self::Approved, self::Scheduled], true);
    }

    /**
     * Ordered steps shown in the editor's workflow tracker.
     *
     * @return list<self>
     */
    public static function trackerSteps(): array
    {
        return [self::Draft, self::InReview, self::Approved, self::Scheduled, self::Published];
    }
}
