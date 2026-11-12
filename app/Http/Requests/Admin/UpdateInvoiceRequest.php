<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('invoice')) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'max:255'],
            'tax_rate' => ['nullable', 'decimal:0,2', 'min:0', 'max:100'],
            'due_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array{description: string, tax_rate_bp: int, due_at: string|null, notes: string|null}
     */
    public function invoiceData(): array
    {
        return [
            'description' => $this->validated('description'),
            'tax_rate_bp' => (int) round(((float) ($this->validated('tax_rate') ?? 0)) * 100),
            'due_at' => $this->validated('due_at'),
            'notes' => $this->validated('notes'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['description' => __('Description'), 'tax_rate' => __('Tax rate (%)'), 'due_at' => __('Due date'), 'notes' => __('Notes')];
    }
}
