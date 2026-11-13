<?php

namespace App\Actions\Automation;

use App\Actions\Leads\CreateLead;
use App\Actions\Leads\LogLeadActivity;
use App\Actions\Radar\ImportRadarAlertEmail;
use App\Actions\Support\OpenSupportTicket;
use App\Enums\ActorType;
use App\Enums\AutomationAction;
use App\Enums\BudgetRange;
use App\Enums\LeadActivityType;
use App\Enums\LeadSource;
use App\Enums\TicketCategory;
use App\Enums\TicketPriority;
use App\Models\AutomationWorkflow;
use App\Models\Customer;
use App\Models\Language;
use App\Models\Lead;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Runs the one whitelisted action an inbound n8n endpoint is configured for, as its acting staff member (who must
 * still be active and hold the action's permission). Input is validated like the admin forms; the request can
 * never choose a different action or run anything else.
 */
class RunInboundAutomation
{
    public function __construct(
        private CreateLead $createLead,
        private LogLeadActivity $logActivity,
        private OpenSupportTicket $openTicket,
        private ImportRadarAlertEmail $importEmail,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     *
     * @throws AuthorizationException
     */
    public function handle(AutomationWorkflow $workflow, array $input): array
    {
        $action = $workflow->action ?? throw new AuthorizationException('No action configured.');
        $user = $workflow->actingUser;

        if (! $user instanceof User || ! $user->is_active || ! $user->can($action->permission()->value)) {
            throw new AuthorizationException(__('The acting staff member may not perform this action.'));
        }

        $result = match ($action) {
            AutomationAction::CreateLead => $this->createLead($input, $user),
            AutomationAction::AddLeadNote => $this->addLeadNote($input, $user),
            AutomationAction::CreateTicket => $this->createTicket($input, $user),
            AutomationAction::RadarEmail => $this->importEmail->handle($this->validate($input, [
                'platform' => ['nullable', 'string', 'max:40'],
                'from' => ['required', 'string', 'max:255'],
                'subject' => ['nullable', 'string', 'max:500'],
                'html' => ['nullable', 'string', 'max:200000', 'required_without:text'],
                'text' => ['nullable', 'string', 'max:200000'],
                'received_at' => ['nullable', 'date'],
            ])),
        };

        $this->audit->record(
            operation: 'automation.inbound.'.$action->value,
            user: $user,
            actorType: ActorType::System,
            actorLabel: 'n8n: '.$workflow->name,
            after: ['workflow' => $workflow->key, ...array_intersect_key($result, array_flip(['lead', 'ticket', 'activity_id', 'platform', 'created', 'updated', 'unchanged']))],
        );

        return $result;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function createLead(array $input, User $user): array
    {
        $data = $this->validate($input, [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9 ()-]{6,30}$/'],
            'company' => ['nullable', 'string', 'max:160'],
            'country' => ['nullable', 'string', 'size:2', 'alpha'],
            'preferred_locale' => ['required', 'string', Rule::in(Language::active()->keys()->all())],
            'description' => ['nullable', 'string', 'max:5000'],
            'budget_range' => ['nullable', Rule::enum(BudgetRange::class)],
            'utm_source' => ['nullable', 'string', 'max:100'],
            'utm_medium' => ['nullable', 'string', 'max:100'],
            'utm_campaign' => ['nullable', 'string', 'max:100'],
        ]);
        $data['country'] = isset($data['country']) ? Str::upper($data['country']) : null;
        $data['locale'] = $data['preferred_locale'];

        $lead = $this->createLead->handle($data, LeadSource::Automation, creator: $user);

        return ['lead' => $lead->reference];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function addLeadNote(array $input, User $user): array
    {
        $data = $this->validate($input, [
            'lead_reference' => ['required', 'string', 'max:20', Rule::exists('leads', 'reference')->whereNull('deleted_at')],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $lead = Lead::query()->where('reference', $data['lead_reference'])->firstOrFail();
        $activity = $this->logActivity->handle($lead, LeadActivityType::Note, $data['body'], $user);

        return ['lead' => $lead->reference, 'activity_id' => $activity->id];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function createTicket(array $input, User $user): array
    {
        $data = $this->validate($input, [
            'customer_reference' => ['required', 'string', 'max:20', Rule::exists('customers', 'reference')->whereNull('deleted_at')],
            'subject' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:'.config('codefanz.support.reply_max_length', 5000)],
            'category' => ['nullable', Rule::enum(TicketCategory::class)],
            'priority' => ['nullable', Rule::enum(TicketPriority::class)],
        ]);

        $customer = Customer::query()->where('reference', $data['customer_reference'])->firstOrFail();
        $ticket = $this->openTicket->handle($customer, [
            'subject' => $data['subject'],
            'category' => $data['category'] ?? TicketCategory::Question->value,
            'priority' => $data['priority'] ?? TicketPriority::Normal->value,
            'project_id' => null,
            'body' => $data['body'],
        ], $user);

        return ['ticket' => $ticket->reference];
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    private function validate(array $input, array $rules): array
    {
        return Validator::make($input, $rules)->validate();
    }
}
