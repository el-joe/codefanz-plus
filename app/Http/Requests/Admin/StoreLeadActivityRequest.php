<?php

namespace App\Http\Requests\Admin;

use App\Enums\LeadActivityType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Staff may log notes, calls, emails and meetings. Status, assignment and system entries are
 * written by the application only.
 */
class StoreLeadActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('lead')) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(array_map(fn (LeadActivityType $type): string => $type->value, LeadActivityType::manual()))],
            'body' => ['required', 'string', 'max:5000'],
        ];
    }
}
