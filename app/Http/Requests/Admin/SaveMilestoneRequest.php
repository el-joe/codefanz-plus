<?php

namespace App\Http\Requests\Admin;

use App\Models\Milestone;
use App\Services\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveMilestoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('project')) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['currency' => mb_strtoupper(trim((string) $this->input('currency', 'USD')))]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:5000'],
            'due_date' => ['nullable', 'date'],
            'amount' => ['nullable', 'decimal:0,2', 'min:0', 'max:999999999'],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'dependencies' => ['nullable', 'array', 'max:50'],
            'dependencies.*' => ['integer'],
            'lock_version' => [$this->route('milestone') instanceof Milestone ? 'required' : 'nullable', 'integer'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function milestoneData(): array
    {
        return [
            ...$this->safe()->only(['title', 'description', 'due_date', 'currency']),
            'amount_minor' => Money::toMinor($this->validated('amount')) ?? 0,
        ];
    }

    /**
     * @return list<int>
     */
    public function dependencyIds(): array
    {
        return array_map('intval', (array) $this->validated('dependencies', []));
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['title' => __('Title'), 'amount' => __('Amount'), 'due_date' => __('Due date')];
    }
}
