<?php

namespace App\Http\Requests\Admin;

use App\Enums\CustomerStatus;
use App\Enums\CustomerType;
use App\Models\Customer;
use App\Models\Language;
use App\Models\Lead;
use App\Services\Countries;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $customer = $this->route('customer');

        return $customer instanceof Customer
            ? $this->user()->can('update', $customer)
            : $this->user()->can('create', Customer::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => filled($this->input('email')) ? mb_strtolower(trim((string) $this->input('email'))) : null,
            'country' => filled($this->input('country')) ? mb_strtoupper((string) $this->input('country')) : null,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(CustomerType::class)],
            'status' => ['required', Rule::enum(CustomerStatus::class)],
            'company_name' => ['required', 'string', 'max:160'],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\-\s.]{5,30}$/'],
            'country' => ['nullable', 'string', Rule::in(Countries::CODES)],
            'tax_id' => ['nullable', 'string', 'max:50'],
            'billing_address_line1' => ['nullable', 'string', 'max:200'],
            'billing_address_line2' => ['nullable', 'string', 'max:200'],
            'billing_city' => ['nullable', 'string', 'max:100'],
            'billing_state' => ['nullable', 'string', 'max:100'],
            'billing_postal_code' => ['nullable', 'string', 'max:20'],
            'preferred_locale' => ['required', Rule::in(Language::active()->keys()->all())],
            'notes' => ['nullable', 'string', 'max:5000'],
            // Only when creating: the won lead this customer is converted from.
            'lead' => [Rule::prohibitedIf($this->route('customer') instanceof Customer), 'nullable', 'string', Rule::exists('leads', 'ulid')->whereNull('deleted_at')],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function customerData(): array
    {
        return collect($this->validated())->except('lead')->all();
    }

    public function lead(): ?Lead
    {
        $ulid = $this->validated('lead');

        return $ulid ? Lead::query()->where('ulid', $ulid)->firstOrFail() : null;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'company_name' => __('Customer name'),
            'billing_address_line1' => __('Address line 1'),
            'billing_address_line2' => __('Address line 2'),
        ];
    }
}
