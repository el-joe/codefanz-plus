<?php

namespace App\Listeners;

use App\Enums\AutomationEvent;
use App\Events\LeadCreated;
use App\Events\LeadStatusChanged;
use App\Events\PaymentSucceeded;
use App\Events\RadarProjectScored;
use App\Events\SupportTicketOpened;
use App\Models\AutomationWorkflow;
use App\Services\Automation\AutomationDispatcher;
use App\Services\Automation\AutomationPayloads;

/**
 * Domain events → signed n8n deliveries. Laravel stays the source of truth: n8n is told what happened, it never
 * decides it (a payment is already verified before PaymentSucceeded fires).
 */
class QueueAutomationWebhooks
{
    public function __construct(private AutomationDispatcher $dispatcher, private AutomationPayloads $payloads) {}

    public function handleLeadCreated(LeadCreated $event): void
    {
        $this->dispatcher->dispatch(AutomationEvent::LeadCreated, fn (AutomationWorkflow $workflow): array => [
            'lead' => $this->payloads->lead($event->lead, $workflow->include_personal_data),
        ]);
    }

    public function handleLeadStatusChanged(LeadStatusChanged $event): void
    {
        $this->dispatcher->dispatch(AutomationEvent::LeadStatusChanged, fn (AutomationWorkflow $workflow): array => [
            'lead' => $this->payloads->lead($event->lead, $workflow->include_personal_data),
            'from' => $event->from->value,
            'to' => $event->to->value,
        ]);
    }

    public function handlePaymentSucceeded(PaymentSucceeded $event): void
    {
        $this->dispatcher->dispatch(AutomationEvent::PaymentSucceeded, fn (): array => [
            'payment' => $this->payloads->payment($event->payment),
        ]);
    }

    public function handleSupportTicketOpened(SupportTicketOpened $event): void
    {
        $this->dispatcher->dispatch(AutomationEvent::TicketOpened, fn (AutomationWorkflow $workflow): array => [
            'ticket' => $this->payloads->ticket($event->ticket, $workflow->include_personal_data),
        ]);
    }

    public function handleRadarProjectScored(RadarProjectScored $event): void
    {
        $this->dispatcher->dispatch(AutomationEvent::RadarProjectScored, fn (): array => [
            'project' => $this->payloads->radarProject($event->project),
        ]);
    }
}
