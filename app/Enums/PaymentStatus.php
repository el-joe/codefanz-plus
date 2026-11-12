<?php

namespace App\Enums;

/**
 * Payment ledger status. Only App\Actions\Payments\VerifyPayment moves a payment to Paid (after server-side
 * verification with the gateway); refunds (next slice) move it to Refunded / PartiallyRefunded.
 */
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
    case PartiallyRefunded = 'partially_refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Paid => __('Paid'),
            self::Failed => __('Failed'),
            self::Cancelled => __('Cancelled'),
            self::Refunded => __('Refunded'),
            self::PartiallyRefunded => __('Partially refunded'),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'info',
            self::Paid => 'success',
            self::Failed => 'danger',
            self::Cancelled => 'neutral',
            self::Refunded, self::PartiallyRefunded => 'warning',
        };
    }

    /**
     * Money was captured (and possibly partly returned): counts towards collected amounts.
     */
    public function isCollected(): bool
    {
        return in_array($this, [self::Paid, self::PartiallyRefunded], true);
    }

    /**
     * Statuses a verification result may move a payment out of. Collected and refunded payments are settled.
     */
    public function isOpen(): bool
    {
        return in_array($this, [self::Pending, self::Failed, self::Cancelled], true);
    }

    /**
     * @return list<self>
     */
    public static function collected(): array
    {
        return [self::Paid, self::PartiallyRefunded];
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $status): array => [$status->value => $status->label()])->all();
    }
}
