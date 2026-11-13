<?php

namespace App\Http\Requests\Admin;

use App\Enums\Permission;
use App\Models\Language;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateRadarProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::RadarManage->value) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'locale' => ['required', 'string', Rule::in(Language::active()->keys()->all())],
            'facts' => ['nullable', 'string', 'max:2000'],
            'price' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function attributes(): array
    {
        return ['facts' => __('Facts about our experience'), 'price' => __('Price or rate to quote')];
    }
}
