<?php

namespace App\Http\Requests\Admin;

use App\Models\Refund;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Finance returned the money outside the gateway (bank transfer, cash) and records its external reference.
 */
class CompleteManualRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Refund $refund */
        $refund = $this->route('refund');

        return $this->user()?->can('complete', $refund) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'external_reference' => ['required', 'string', 'max:191', 'regex:/^[\pL\pN ._:\/#-]+$/u'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['external_reference' => __('External reference')];
    }
}
