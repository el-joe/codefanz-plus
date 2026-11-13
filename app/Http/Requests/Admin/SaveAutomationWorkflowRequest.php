<?php

namespace App\Http\Requests\Admin;

use App\Enums\AutomationAction;
use App\Enums\AutomationDirection;
use App\Enums\AutomationEvent;
use App\Enums\Permission;
use App\Models\User;
use App\Services\Security\OutboundUrlGuard;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * B10 workflow form. Outbound workflows need an https webhook URL and at least one event; inbound endpoints need
 * one whitelisted action and an active staff member who holds that action's permission.
 */
class SaveAutomationWorkflowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::AutomationManage->value) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'events' => array_values((array) $this->input('events', [])),
            'include_personal_data' => $this->boolean('include_personal_data'),
            'is_enabled' => $this->boolean('is_enabled'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $outbound = $this->input('direction') === AutomationDirection::Outbound->value;
        $local = app()->environment('local', 'testing');

        return [
            'name' => ['required', 'string', 'max:120'],
            'direction' => ['required', Rule::enum(AutomationDirection::class)],
            'url' => $outbound ? ['required', 'string', 'max:500', 'url:'.($local ? 'http,https' : 'https')] : ['nullable', 'prohibited'],
            'events' => $outbound ? ['required', 'array', 'min:1'] : ['array', 'max:0'],
            'events.*' => ['string', Rule::in(array_keys(AutomationEvent::subscribable()))],
            'action' => $outbound ? ['nullable', 'prohibited'] : ['required', Rule::enum(AutomationAction::class)],
            'acts_as_user_id' => $outbound ? ['nullable', 'prohibited'] : ['required', 'integer', Rule::exists('users', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'include_personal_data' => ['boolean'],
            'is_enabled' => ['boolean'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            // SSRF: an outbound webhook may only target a public host (ALLOW_PRIVATE_OUTBOUND lifts this locally).
            function (Validator $validator): void {
                if ($this->input('direction') === AutomationDirection::Outbound->value && ! $validator->errors()->has('url')
                    && ! app(OutboundUrlGuard::class)->isAllowed((string) $this->input('url'))) {
                    $validator->errors()->add('url', __('The webhook URL must point to a public host.'));
                }
            },
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['action', 'acts_as_user_id']) || $this->input('direction') !== AutomationDirection::Inbound->value) {
                    return;
                }

                $user = User::query()->find($this->integer('acts_as_user_id'));
                $action = AutomationAction::from((string) $this->input('action'));

                if ($user === null || ! $user->can($action->permission()->value)) {
                    $validator->errors()->add('acts_as_user_id', __('This staff member does not have the :permission permission this action needs.', ['permission' => $action->permission()->value]));
                }
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function workflowData(): array
    {
        $data = $this->validated();
        $outbound = $data['direction'] === AutomationDirection::Outbound->value;

        return [
            ...$data,
            'url' => $outbound ? $data['url'] : null,
            'events' => $outbound ? $data['events'] : null,
            'action' => $outbound ? null : $data['action'],
            'acts_as_user_id' => $outbound ? null : $data['acts_as_user_id'],
        ];
    }
}
