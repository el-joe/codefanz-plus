<?php

namespace App\Http\Requests\Admin;

use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Project;
use App\Models\User;
use App\Services\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project instanceof Project
            ? $this->user()?->can('update', $project) ?? false
            : $this->user()?->can('create', Project::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'currency' => mb_strtoupper(trim((string) $this->input('currency', config('codefanz.projects.default_currency')))),
            'show_budget_to_client' => $this->boolean('show_budget_to_client'),
            'show_team_to_client' => $this->boolean('show_team_to_client'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $isNew = ! $this->route('project') instanceof Project;

        return [
            'customer' => [$isNew ? 'required' : 'prohibited', 'string', Rule::exists('customers', 'ulid')->whereNull('deleted_at')],
            'lead' => ['nullable', 'string', Rule::exists('leads', 'ulid')->whereNull('deleted_at')],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:5000'],
            'type' => ['required', Rule::enum(ProjectType::class)],
            'status' => ['required', Rule::enum(ProjectStatus::class)],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'project_manager_id' => ['nullable', 'integer', Rule::in($this->staffIds())],
            'budget' => ['nullable', 'decimal:0,2', 'min:0', 'max:999999999'],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'show_budget_to_client' => ['boolean'],
            'show_team_to_client' => ['boolean'],
            'members' => ['nullable', 'array', 'max:30'],
            'members.*' => ['integer', Rule::in($this->staffIds())],
            'member_roles' => ['nullable', 'array'],
            'member_roles.*' => ['nullable', 'string', 'max:80'],
            'lock_version' => [$isNew ? 'nullable' : 'required', 'integer'],
        ];
    }

    /**
     * Active staff (any non-client role) who can be put on a project.
     *
     * @return list<int>
     */
    public function staffIds(): array
    {
        return once(fn (): array => User::query()->where('is_active', true)
            ->whereHas('roles', fn ($query) => $query->where('name', '!=', 'client'))
            ->pluck('id')->map(fn ($id): int => (int) $id)->all());
    }

    public function customer(): ?Customer
    {
        return $this->filled('customer') ? Customer::query()->where('ulid', $this->validated('customer'))->first() : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function projectData(): array
    {
        $lead = $this->filled('lead') ? Lead::query()->where('ulid', $this->validated('lead'))->first() : null;

        return [
            ...$this->safe()->only(['name', 'description', 'type', 'status', 'start_date', 'due_date', 'project_manager_id', 'currency', 'show_budget_to_client', 'show_team_to_client']),
            'lead_id' => $lead?->getKey(),
            'customer_id' => $this->customer()?->getKey(),
            'budget_minor' => Money::toMinor($this->validated('budget')),
        ];
    }

    /**
     * @return array<int, string|null> user id => role label
     */
    public function members(): array
    {
        $roles = (array) $this->validated('member_roles', []);

        return collect((array) $this->validated('members', []))
            ->mapWithKeys(fn ($id): array => [(int) $id => $roles[$id] ?? null])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('Project name'),
            'due_date' => __('Due date'),
            'start_date' => __('Start date'),
            'budget' => __('Budget'),
            'project_manager_id' => __('Project manager'),
        ];
    }
}
