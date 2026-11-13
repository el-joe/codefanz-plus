<?php

namespace App\Enums;

/**
 * Domain events an outbound n8n workflow can subscribe to.
 */
enum AutomationEvent: string
{
    case LeadCreated = 'lead.created';
    case LeadStatusChanged = 'lead.status_changed';
    case PaymentSucceeded = 'payment.succeeded';
    case TicketOpened = 'ticket.opened';
    case RadarProjectScored = 'radar.project_scored';
    case Test = 'test.ping';

    public function label(): string
    {
        return match ($this) {
            self::LeadCreated => __('New lead'),
            self::LeadStatusChanged => __('Lead status changed'),
            self::PaymentSucceeded => __('Payment verified'),
            self::TicketOpened => __('Support ticket opened'),
            self::RadarProjectScored => __('Radar project scored'),
            self::Test => __('Test event'),
        };
    }

    /**
     * Events a workflow can subscribe to (the test event is sent on demand only).
     *
     * @return array<string, string>
     */
    public static function subscribable(): array
    {
        return collect(self::cases())
            ->reject(fn (self $event): bool => $event === self::Test)
            ->mapWithKeys(fn (self $event): array => [$event->value => $event->label()])
            ->all();
    }
}
