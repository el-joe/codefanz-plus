<?php

namespace App\Http\Requests\Admin;

use App\Enums\Role;
use App\Models\Language;
use App\Models\User;
use DateTimeZone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->route('user');

        return $user instanceof User
            ? $this->user()->can('update', $user)
            : $this->user()->can('create', User::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => mb_strtolower(trim((string) $this->input('email'))),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'locale' => ['required', Rule::in(Language::active()->keys()->all())],
            'timezone' => ['required', Rule::in(DateTimeZone::listIdentifiers())],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['required', 'distinct', Rule::in(array_map(fn (Role $role): string => $role->value, Role::staff()))],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['roles' => __('Roles')];
    }
}
