<?php

namespace App\Enums;

/**
 * Delivery state of one platform variant. A variant is only queued once its post is approved and due; accounts that
 * cannot publish through the API get "ready for manual export" instead of a simulated success.
 */
enum SocialVariantStatus: string
{
    case Pending = 'pending';
    case Queued = 'queued';
    case Publishing = 'publishing';
    case Published = 'published';
    case Failed = 'failed';
    case ReadyForExport = 'ready_for_export';
    case ExportedManually = 'exported_manually';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Waiting for schedule'),
            self::Queued => __('Queued'),
            self::Publishing => __('Publishing'),
            self::Published => __('Published'),
            self::Failed => __('Failed'),
            self::ReadyForExport => __('Ready for manual export'),
            self::ExportedManually => __('Posted manually'),
            self::Cancelled => __('Cancelled'),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending, self::Cancelled => 'neutral',
            self::Queued, self::Publishing => 'brand',
            self::Published, self::ExportedManually => 'success',
            self::Failed => 'danger',
            self::ReadyForExport => 'warning',
        };
    }

    public function isDone(): bool
    {
        return in_array($this, [self::Published, self::ExportedManually, self::Cancelled], true);
    }

    public function canBeMarkedPosted(): bool
    {
        return in_array($this, [self::ReadyForExport, self::Failed], true);
    }
}
