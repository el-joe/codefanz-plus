<?php

namespace App\Http\Requests\Admin;

use App\Models\Payment;
use App\Models\Refund;
use App\Services\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Request a refund on a payment. The cap (collected − refunded − open requests) is enforced in RequestRefund under
 * a lock on the payment.
 */
class StoreRefundRequest extends FormRequest
{
    /**
     * @var string
     */
    protected $errorBag = 'refund';

    public function authorize(): bool
    {
        /** @var Payment $payment */
        $payment = $this->route('payment');

        return $this->user()?->can('create', [Refund::class, $payment]) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'decimal:0,2', 'min:0.01', 'max:999999999'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    public function amountMinor(): int
    {
        return (int) Money::toMinor($this->validated('amount'));
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['amount' => __('Amount'), 'reason' => __('Reason')];
    }
}
