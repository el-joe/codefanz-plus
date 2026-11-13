<?php

namespace App\Http\Requests\Admin;

use App\Enums\Permission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRadarPlatformRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::RadarManage->value) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_enabled' => $this->boolean('is_enabled')]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'is_enabled' => ['boolean'],
            'fetch_interval_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'rate_limit_per_hour' => ['required', 'integer', 'min:0', 'max:1000'],
        ];
    }
}
