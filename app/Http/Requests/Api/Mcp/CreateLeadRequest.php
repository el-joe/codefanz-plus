<?php

namespace App\Http\Requests\Api\Mcp;

use App\Enums\BudgetRange;
use App\Enums\LeadPriority;
use App\Enums\ProjectTimeline;
use App\Models\Language;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * create_lead: a lead the user asked the MCP client to record (source "mcp").
 */
class CreateLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9 ()-]{6,30}$/'],
            'company' => ['nullable', 'string', 'max:160'],
            'country' => ['nullable', 'string', 'size:2', 'alpha'],
            'preferred_locale' => ['required', 'string', Rule::in(Language::active()->keys()->all())],
            'description' => ['nullable', 'string', 'max:5000'],
            'budget_range' => ['nullable', Rule::enum(BudgetRange::class)],
            'timeline' => ['nullable', Rule::enum(ProjectTimeline::class)],
            'priority' => ['nullable', Rule::enum(LeadPriority::class)],
            'services' => ['nullable', 'array', 'max:10'],
            'services.*' => ['integer', 'distinct', Rule::exists('services', 'id')->whereNull('deleted_at')],
        ];
    }
}
