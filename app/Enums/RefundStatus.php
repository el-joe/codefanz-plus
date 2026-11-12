<?php

namespace App\Enums;

/**
 * Refund workflow (four-eyes): requested → approved/rejected by a different finance user → processing at the
 * gateway → succeeded / failed, or manual_required when the gateway has no refund API for the payment (finance
 * then completes it outside and records the external reference).
 */
enum RefundStatus: string
{
    case Requested = 'requested';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Processing = 'processing';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case ManualRequired = 'manual_required';

    public function label(): string
    {
        return match ($this) {
            self::Requested => __('Awaiting finance approval'),
            self::Approved => __('Approved'),
            self::Rejected => __('Rejected'),
            self::Processing => __('Processing at gateway'),
            self::Succeeded => __('Refunded'),
            self::Failed => __('Failed'),
            self::ManualRequired => __('Manual refund required'),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Requested, self::ManualRequired => 'warning',
            self::Approved, self::Processing => 'info',
            self::Succeeded => 'success',
            self::Failed => 'danger',
            self::Rejected => 'neutral',
        };
    }

    /**
     * Statuses that still reserve part of the payment's refundable amount.
     *
     * @return list<self>
     */
    public static function open(): array
    {
        return [self::Requested, self::Approved, self::Processing, self::ManualRequired];
    }

    public function isOpen(): bool
    {
        return in_array($this, self::open(), true);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $status): array => [$status->value => $status->label()])->all();
    }
}
