<?php

namespace Database\Factories;

use App\Enums\AutomationAction;
use App\Enums\AutomationDirection;
use App\Enums\AutomationEvent;
use App\Models\AutomationWorkflow;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AutomationWorkflow>
 */
class AutomationWorkflowFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => 'workflow-'.Str::lower(Str::random(10)),
            'name' => 'New lead to Telegram',
            'direction' => AutomationDirection::Outbound,
            'url' => 'https://n8n.example.test/webhook/'.Str::lower(Str::random(8)),
            'secret' => 'whsec_test_secret_value',
            'events' => [AutomationEvent::LeadCreated->value],
            'is_enabled' => true,
            'include_personal_data' => false,
        ];
    }

    public function inbound(AutomationAction $action, User $actingUser): static
    {
        return $this->state([
            'direction' => AutomationDirection::Inbound,
            'url' => null,
            'events' => null,
            'action' => $action,
            'acts_as_user_id' => $actingUser->id,
        ]);
    }
}
