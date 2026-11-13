<?php

namespace App\Actions\Radar;

use App\Actions\Leads\CreateLead;
use App\Enums\LeadSource;
use App\Enums\RadarProjectStatus;
use App\Models\Lead;
use App\Models\RadarProject;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Turns a radar project into a CRM lead (source "radar") through CreateLead, so the timeline, audit, LeadCreated
 * event and team notification all happen as for any other lead. No auto-reply is sent: platform clients are
 * contacted on the platform.
 */
class ConvertRadarProjectToLead
{
    public function __construct(private CreateLead $createLead, private AuditLogger $audit) {}

    /**
     * @param  array{name: string, email: string, company?: string|null, preferred_locale: string}  $contact
     */
    public function handle(RadarProject $project, array $contact, User $user): Lead
    {
        $project->loadMissing('platform');

        if ($project->lead_id !== null) {
            throw ValidationException::withMessages(['project' => __('This project is already a lead.')]);
        }

        $amount = $project->budget_max_minor ?? $project->budget_min_minor;

        return DB::transaction(function () use ($project, $contact, $user, $amount): Lead {
            $lead = $this->createLead->handle([
                'name' => $contact['name'],
                'email' => $contact['email'],
                'company' => $contact['company'] ?? null,
                'country' => $project->client_country,
                'preferred_locale' => $contact['preferred_locale'],
                'locale' => $contact['preferred_locale'],
                'description' => mb_substr($project->title."\n\n".$project->description, 0, 5000),
                'existing_url' => mb_substr($project->url, 0, 500),
                'estimated_value_minor' => $amount !== null && $project->currency !== null ? $amount : null,
                'currency' => $amount !== null ? $project->currency : null,
                'referrer' => mb_substr($project->url, 0, 500),
                'brief' => [
                    'radar_project_id' => $project->id,
                    'platform' => $project->platform?->name,
                    'url' => $project->url,
                    'budget' => $project->budgetLabel(),
                    'skills' => $project->skillList(),
                    'rule_score' => $project->score,
                    'ai_score' => $project->ai_score,
                ],
            ], LeadSource::Radar, creator: $user);

            $project->forceFill([
                'lead_id' => $lead->id,
                'status' => in_array($project->status, [RadarProjectStatus::New, RadarProjectStatus::Shortlisted, RadarProjectStatus::ProposalDrafted], true) ? RadarProjectStatus::Applied : $project->status,
                'status_changed_by' => $user->getKey(),
            ])->save();

            $this->audit->record(operation: 'radar.converted_to_lead', auditable: $project, user: $user, after: ['lead' => $lead->reference]);

            return $lead;
        });
    }
}
