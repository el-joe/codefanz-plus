<?php

namespace App\Http\Requests\Admin;

use App\Models\Refund;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Approve or reject a refund (four-eyes). A rejection needs a note for the requester.
 */
class DecideRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Refund $refund */
        $refund = $this->route('refund');

        return $this->user()?->can('approve', $refund) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'note' => [$this->routeIs('admin.refunds.reject') ? 'required' : 'nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['note' => __('Note')];
    }
}
