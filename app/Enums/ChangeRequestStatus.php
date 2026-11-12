<?php

namespace App\Enums;

enum ChangeRequestStatus: string
{
    case Pending = 'pending';
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case ChangesRequested = 'changes_requested';
    case Applied = 'applied';
    case Failed = 'failed';

    public function isReviewable(): bool
    {
        return in_array($this, [self::Pending, self::UnderReview], true);
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending, self::ChangesRequested => 'warning',
            self::UnderReview => 'info',
            self::Approved => 'brand',
            self::Applied => 'success',
            self::Rejected, self::Failed => 'danger',
        };
    }
}
