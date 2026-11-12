<?php

namespace App\Enums;

/**
 * What triggered a payment state change in the append-only payment_events history.
 */
enum PaymentEventSource: string
{
    case Checkout = 'checkout';
    case Webhook = 'webhook';
    case ReturnUrl = 'return_url';
    case Admin = 'admin';
    case Reconciliation = 'reconciliation';

    public function label(): string
    {
        return match ($this) {
            self::Checkout => __('Checkout started'),
            self::Webhook => __('Webhook + API'),
            self::ReturnUrl => __('Return + API'),
            self::Admin => __('Staff check + API'),
            self::Reconciliation => __('Reconciliation + API'),
        };
    }
}
