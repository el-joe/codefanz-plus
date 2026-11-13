<?php

namespace App\Enums;

/**
 * The only things an inbound n8n request can do. Each runs through the same action class as the admin, with the
 * permissions of the endpoint's acting staff member; there is no generic or code-executing action.
 */
enum AutomationAction: string
{
    case CreateLead = 'create_lead';
    case AddLeadNote = 'add_lead_note';
    case CreateTicket = 'create_ticket';
    case RadarEmail = 'radar_email';

    public function label(): string
    {
        return match ($this) {
            self::CreateLead => __('Create a lead'),
            self::AddLeadNote => __('Add a note to a lead'),
            self::CreateTicket => __('Open a support ticket'),
            self::RadarEmail => __('Radar: import a platform alert email'),
        };
    }

    /**
     * Permission the acting staff member must hold.
     */
    public function permission(): Permission
    {
        return match ($this) {
            self::CreateLead, self::AddLeadNote => Permission::LeadsManage,
            self::CreateTicket => Permission::SupportManage,
            self::RadarEmail => Permission::RadarManage,
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $action): array => [$action->value => $action->label()])->all();
    }
}
