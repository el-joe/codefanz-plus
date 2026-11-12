<?php

namespace App\Enums;

/**
 * Support ticket lifecycle. A staff reply waits on the customer, a customer reply waits on support, resolved
 * tickets close automatically after `codefanz.support.auto_close_after_days`. Closed tickets are final.
 */
enum TicketStatus: string
{
    case Open = 'open';
    case AwaitingCustomer = 'awaiting_customer';
    case AwaitingSupport = 'awaiting_support';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => __('Open'),
            self::AwaitingCustomer => __('Awaiting customer'),
            self::AwaitingSupport => __('Awaiting support'),
            self::Resolved => __('Resolved'),
            self::Closed => __('Closed'),
        };
    }

    /**
     * Wording for the client in the portal.
     */
    public function clientLabel(): string
    {
        return match ($this) {
            self::Open => __('Open'),
            self::AwaitingCustomer => __('Waiting on you'),
            self::AwaitingSupport => __('In progress'),
            self::Resolved => __('Resolved'),
            self::Closed => __('Closed'),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Open => 'info',
            self::AwaitingCustomer => 'warning',
            self::AwaitingSupport => 'brand',
            self::Resolved => 'success',
            self::Closed => 'neutral',
        };
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Resolved, self::Closed], true);
    }

    /**
     * @return list<self>
     */
    public static function open(): array
    {
        return array_values(array_filter(self::cases(), fn (self $status): bool => $status->isOpen()));
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $status): array => [$status->value => $status->label()])->all();
    }
}
