<?php

namespace App\Http\Requests\Admin;

use App\Enums\Permission;
use App\Models\Redirect;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveRedirectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::SeoManage->value) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'from_path' => ['required', 'string', 'max:500'],
            'to_path' => ['nullable', 'required_unless:status_code,410', 'string', 'max:1000'],
            'status_code' => ['required', 'integer', Rule::in(Redirect::STATUS_CODES)],
            'is_active' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'from_path' => __('From'),
            'to_path' => __('To'),
            'status_code' => __('Type'),
        ];
    }
}
