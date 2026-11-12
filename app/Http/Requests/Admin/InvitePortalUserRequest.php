<?php

namespace App\Http\Requests\Admin;

use App\Enums\CustomerUserRole;
use App\Models\Language;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InvitePortalUserRequest extends FormRequest
{
    /**
     * Errors go to their own bag so they show next to the invite form, not the customer details.
     *
     * @var string
     */
    protected $errorBag = 'invitePortalUser';

    public function authorize(): bool
    {
        return $this->user()->can('managePortalAccess', $this->route('customer'));
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => mb_strtolower(trim((string) $this->input('email')))]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'locale' => ['required', Rule::in(Language::active()->keys()->all())],
            'role' => ['required', Rule::enum(CustomerUserRole::class)],
        ];
    }
}
