<?php

namespace App\Actions\Assistant;

use App\Actions\Leads\CreateLead;
use App\Enums\ChatSessionStatus;
use App\Enums\LeadSource;
use App\Models\ChatSession;
use App\Models\Lead;

/**
 * Turns a confirmed assistant brief into a CRM lead through CreateLead (so the timeline, audit entry,
 * LeadCreated event, team notifications and visitor auto-reply all happen) and links the transcript.
 */
class SubmitAssistantBrief
{
    public function __construct(private CreateLead $createLead) {}

    /**
     * @param  array{name: string, email: string, phone?: string|null, company?: string|null, preferred_contact: string, preferred_locale: string}  $contact
     */
    public function handle(ChatSession $session, array $contact, ?string $ip, ?string $userAgent): Lead
    {
        $brief = $session->brief ?? [];
        $description = trim(implode("\n\n", array_filter([$brief['project_type'] ?? null, $brief['description'] ?? null])));

        $lead = $this->createLead->handle([
            'name' => $contact['name'],
            'email' => $contact['email'],
            'phone' => filled($contact['phone'] ?? null) ? trim((string) $contact['phone']) : ($brief['phone'] ?? null),
            'company' => filled($contact['company'] ?? null) ? trim((string) $contact['company']) : ($brief['company'] ?? null),
            'country' => $brief['country'] ?? null,
            'services' => $brief['services'] ?? [],
            'platforms' => $brief['platforms'] ?? [],
            'description' => $description,
            'features' => implode("\n", $brief['features'] ?? []),
            'goals' => $brief['goals'] ?? null,
            'technologies' => $brief['technologies'] ?? [],
            'existing_url' => $brief['existing_url'] ?? null,
            'budget_range' => $brief['budget_range'] ?? null,
            'timeline' => $brief['timeline'] ?? null,
            'preferred_contact' => $contact['preferred_contact'],
            'preferred_locale' => $contact['preferred_locale'],
            'brief' => ['assistant' => array_filter([
                'session' => $session->ulid,
                'mode' => $session->mode->value,
                'fallback_reason' => $session->fallback_reason,
                'project_type' => $brief['project_type'] ?? null,
                'existing_system' => $brief['existing_system'] ?? null,
                'technology_notes' => $brief['technology_notes'] ?? null,
                'preliminary' => true,
            ], fn ($value): bool => $value !== null && $value !== [])],
            'locale' => $session->locale,
            'ip_address' => $ip,
            'user_agent' => $userAgent !== null ? mb_substr($userAgent, 0, 255) : null,
            'consent_at' => now(),
        ], LeadSource::AiAssistant, sendAutoReply: true);

        $session->forceFill([
            'lead_id' => $lead->getKey(),
            'status' => ChatSessionStatus::Completed,
            'completed_at' => now(),
            'brief' => [...$brief, ...array_filter($contact, fn ($value): bool => filled($value))],
        ])->save();

        return $lead;
    }
}
