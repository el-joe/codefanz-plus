<?php

namespace App\Http\Requests\Admin;

use App\Enums\Permission;
use App\Models\Language;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Contact details for the lead. Platform clients rarely share an email before an interview, so the form is
 * prefilled with a non-deliverable placeholder (*.invalid) that staff replace once they have the real one.
 */
class ConvertRadarProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can(Permission::RadarManage->value) ?? false) && $this->user()->can(Permission::LeadsManage->value);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'company' => ['nullable', 'string', 'max:160'],
            'preferred_locale' => ['required', 'string', Rule::in(Language::active()->keys()->all())],
        ];
    }
}
