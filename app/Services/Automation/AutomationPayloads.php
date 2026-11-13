<?php

namespace App\Services\Automation;

use App\Models\Lead;
use App\Models\Payment;
use App\Models\RadarProject;
use App\Models\SupportTicket;

/**
 * Event payloads sent to n8n. Personal data (names, emails, phone numbers, ticket subjects) is only included for
 * workflows that explicitly allow it; every payload carries references and an admin link instead.
 */
class AutomationPayloads
{
    /**
     * @return array<string, mixed>
     */
    public function lead(Lead $lead, bool $personalData): array
    {
        return array_filter([
            'reference' => $lead->reference,
            'source' => $lead->source->value,
            'status' => $lead->status->value,
            'priority' => $lead->priority->value,
            'preferred_locale' => $lead->preferred_locale,
            'country' => $lead->country,
            'budget_range' => $lead->budget_range?->value,
            'created_at' => $lead->created_at?->toIso8601String(),
            'admin_url' => route('admin.leads.show', $lead),
            ...($personalData ? [
                'name' => $lead->name,
                'email' => $lead->email,
                'phone' => $lead->phone,
                'company' => $lead->company,
            ] : []),
        ], fn ($value): bool => $value !== null);
    }

    /**
     * @return array<string, mixed>
     */
    public function payment(Payment $payment): array
    {
        return [
            'reference' => $payment->reference,
            'gateway' => $payment->gateway,
            'amount_minor' => $payment->amount_minor,
            'currency' => $payment->currency,
            'status' => $payment->status->value,
            'paid_at' => $payment->paid_at?->toIso8601String(),
            'admin_url' => route('admin.payments.show', $payment),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function ticket(SupportTicket $ticket, bool $personalData): array
    {
        $ticket->loadMissing('customer');

        return array_filter([
            'reference' => $ticket->reference,
            'customer' => $ticket->customer?->reference,
            'category' => $ticket->category->value,
            'priority' => $ticket->priority->value,
            'status' => $ticket->status->value,
            'subject' => $personalData ? $ticket->subject : null,
            'admin_url' => route('admin.tickets.show', $ticket),
        ], fn ($value): bool => $value !== null);
    }

    /**
     * @return array<string, mixed>
     */
    public function radarProject(RadarProject $project): array
    {
        $project->loadMissing('platform');

        return [
            'id' => $project->id,
            'platform' => $project->platform?->key,
            'title' => $project->title,
            'url' => $project->url,
            'budget' => $project->budgetLabel(),
            'skills' => $project->skillList(),
            'language' => $project->language,
            'status' => $project->status->value,
            'score' => $project->score,
            'ai_score' => $project->ai_score,
            'admin_url' => route('admin.radar.projects.show', $project),
        ];
    }
}
