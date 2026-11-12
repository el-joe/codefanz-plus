<?php

namespace App\Http\Requests\Admin;

use App\Models\Milestone;
use App\Models\PaymentLink;
use App\Models\Project;
use App\Services\Money;
use App\Services\Payments\PaymentGatewayManager;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Create payment link dialog (A04). The remaining-balance cap is enforced again in CreatePaymentLink under a lock.
 */
class StorePaymentLinkRequest extends FormRequest
{
    /**
     * @var string
     */
    protected $errorBag = 'paymentLink';

    public function authorize(): bool
    {
        return ($this->user()?->can('create', PaymentLink::class) ?? false) && $this->user()->can('view', $this->route('project'));
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['currency' => mb_strtoupper(trim((string) $this->input('currency', '')))]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Project $project */
        $project = $this->route('project');

        return [
            'milestone' => ['nullable', 'string', Rule::exists('milestones', 'ulid')->where('project_id', $project->getKey())],
            'amount' => ['required', 'decimal:0,2', 'min:0.01', 'max:999999999'],
            'currency' => ['required', 'string', Rule::in((array) config('codefanz.payments.currencies'))],
            'description' => ['nullable', 'string', 'max:255'],
            'expires_on' => ['nullable', 'date', 'after_or_equal:today'],
            'usage' => ['required', Rule::in(['single', 'reusable'])],
            'gateways' => ['required', 'array', 'min:1'],
            'gateways.*' => ['string', Rule::in(app(PaymentGatewayManager::class)->keys())],
        ];
    }

    /**
     * @return array{milestone_id: int|null, amount_minor: int, currency: string, description: string|null, expires_at: CarbonInterface|null, single_use: bool, gateways: list<string>}
     */
    public function linkData(): array
    {
        $milestone = filled($this->validated('milestone'))
            ? Milestone::query()->where('ulid', $this->validated('milestone'))->value('id')
            : null;

        return [
            'milestone_id' => $milestone,
            'amount_minor' => (int) Money::toMinor($this->validated('amount')),
            'currency' => $this->validated('currency'),
            'description' => $this->validated('description'),
            'expires_at' => filled($this->validated('expires_on')) ? Carbon::parse($this->validated('expires_on'))->endOfDay() : null,
            'single_use' => $this->validated('usage') === 'single',
            'gateways' => array_values((array) $this->validated('gateways')),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'milestone' => __('Milestone'),
            'amount' => __('Amount'),
            'currency' => __('Currency'),
            'description' => __('Description'),
            'expires_on' => __('Expires on'),
            'usage' => __('Usage'),
            'gateways' => __('Allowed gateways'),
        ];
    }
}
