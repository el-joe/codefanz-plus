<?php

namespace App\Http\Requests\Admin;

use App\Enums\BudgetRange;
use App\Enums\ContactMethod;
use App\Enums\LeadPriority;
use App\Enums\ProjectPlatform;
use App\Enums\ProjectTimeline;
use App\Models\Language;
use App\Models\Lead;
use App\Models\User;
use App\Services\Countries;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Manual lead creation and staff edits of a lead's details.
 */
class SaveLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        $lead = $this->route('lead');

        return $lead instanceof Lead
            ? ($this->user()?->can('update', $lead) ?? false)
            : ($this->user()?->can('create', Lead::class) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $isUpdate = $this->route('lead') instanceof Lead;

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9 ()-]{6,30}$/'],
            'company' => ['nullable', 'string', 'max:160'],
            'country' => ['nullable', 'string', Rule::in(Countries::CODES)],
            'preferred_locale' => ['required', Rule::in(Language::active()->keys()->all())],
            'preferred_contact' => ['required', Rule::enum(ContactMethod::class)],
            'platforms' => ['nullable', 'array'],
            'platforms.*' => ['distinct', Rule::enum(ProjectPlatform::class)],
            'services' => ['nullable', 'array'],
            'services.*' => ['integer', 'distinct', Rule::exists('services', 'id')->whereNull('deleted_at')],
            'technologies' => ['nullable', 'array'],
            'technologies.*' => ['integer', 'distinct', Rule::exists('technologies', 'id')->whereNull('deleted_at')],
            'description' => ['nullable', 'string', 'max:5000'],
            'features' => ['nullable', 'string', 'max:5000'],
            'goals' => ['nullable', 'string', 'max:5000'],
            'existing_url' => ['nullable', 'string', 'url:http,https', 'max:500'],
            'budget_range' => ['nullable', Rule::enum(BudgetRange::class)],
            'timeline' => ['nullable', Rule::enum(ProjectTimeline::class)],
            'deadline' => ['nullable', 'date_format:Y-m-d'],
            'priority' => ['required', Rule::enum(LeadPriority::class)],
            'follow_up_at' => ['nullable', 'date'],
            'estimated_value' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'currency' => ['nullable', 'required_with:estimated_value', 'string', 'size:3', 'alpha'],
            'assigned_to' => [$isUpdate ? 'prohibited' : 'nullable', 'integer', Rule::in(User::query()->leadManagers()->pluck('id')->all())],
            'lock_version' => [$isUpdate ? 'required' : 'nullable', 'integer'],
        ];
    }

    /**
     * Validated data in Lead's shape (money in minor units, upper-case currency).
     *
     * @return array<string, mixed>
     */
    public function leadData(): array
    {
        $data = $this->safe()->except(['estimated_value', 'lock_version']);
        $value = $this->validated('estimated_value');

        $data['estimated_value_minor'] = $value !== null ? (int) round(((float) $value) * 100) : null;
        $data['currency'] = $value !== null ? mb_strtoupper((string) ($data['currency'] ?? config('codefanz.leads.default_currency'))) : null;
        $data['platforms'] = array_values($data['platforms'] ?? []) ?: null;
        $data['services'] ??= [];
        $data['technologies'] ??= [];

        return $data;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'assigned_to.in' => __('Leads can only be assigned to active staff who manage leads.'),
        ];
    }
}
