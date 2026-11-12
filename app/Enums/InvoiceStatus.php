<?php

namespace App\Enums;

/**
 * Drafts have no number yet; issuing assigns the next gap-free number and freezes the customer snapshot.
 */
enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Issued = 'issued';
    case Paid = 'paid';
    case Void = 'void';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Issued => __('Unpaid'),
            self::Paid => __('Paid'),
            self::Void => __('Void'),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::Issued => 'warning',
            self::Paid => 'success',
            self::Void => 'neutral',
        };
    }

    /**
     * Statuses the client may see in the portal.
     *
     * @return list<self>
     */
    public static function visibleToClient(): array
    {
        return [self::Issued, self::Paid, self::Void];
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $status): array => [$status->value => $status->label()])->all();
    }
}
