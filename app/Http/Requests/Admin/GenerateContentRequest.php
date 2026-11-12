<?php

namespace App\Http\Requests\Admin;

use App\Enums\Permission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::AiUse->value) ?? false;
    }

    /**
     * Variable values are validated against the template's own declarations in GenerateContent.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'template' => ['required', 'string', Rule::exists('prompt_templates', 'key')->where('is_active', true)->whereNull('deleted_at')],
            'target_type' => ['nullable', 'string', 'max:40'],
            'target_id' => ['nullable', 'integer', 'min:1'],
            'locales' => ['required', 'array', 'min:1'],
            'locales.*' => ['string', Rule::exists('languages', 'code')->where('is_active', true)],
            'translate_from_en' => ['nullable', 'boolean'],
            'inputs' => ['nullable', 'array'],
            'inputs.*' => ['nullable', 'string', 'max:20000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['locales' => __('Languages'), 'target_id' => __('Website item')];
    }
}
