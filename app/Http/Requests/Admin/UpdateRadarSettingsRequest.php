<?php

namespace App\Http\Requests\Admin;

use App\Enums\Permission;
use App\Models\Language;
use App\Services\Radar\RadarSettings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Scoring weights (0–100 each, at least one above zero), alert thresholds (immediate ≥ normal ≥ digest), our
 * extra keywords, budget targets, working languages, digest size and the AI auto-score switch.
 */
class UpdateRadarSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::RadarManage->value) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'keywords' => array_values(array_unique(array_filter(array_map('trim', preg_split('/[\n,،]+/u', (string) $this->input('keywords', '')) ?: []), 'filled'))),
            'languages' => array_values((array) $this->input('languages', [])),
            'ai_auto_score' => $this->boolean('ai_auto_score'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'thresholds.immediate' => ['required', 'integer', 'min:1', 'max:100'],
            'thresholds.normal' => ['required', 'integer', 'min:1', 'max:100', 'lte:thresholds.immediate'],
            'thresholds.digest' => ['required', 'integer', 'min:1', 'max:100', 'lte:thresholds.normal'],
            'keywords' => ['array', 'max:200'],
            'keywords.*' => ['string', 'max:60'],
            'target_budget_usd' => ['required', 'integer', 'min:1', 'max:10000000'],
            'target_hourly_usd' => ['required', 'integer', 'min:1', 'max:10000'],
            'languages' => ['required', 'array', 'min:1'],
            'languages.*' => ['string', Rule::in(Language::active()->keys()->all())],
            'digest_size' => ['required', 'integer', 'min:1', 'max:50'],
            'ai_auto_score' => ['boolean'],
        ];

        foreach (RadarSettings::FACTORS as $factor) {
            $rules['weights.'.$factor] = ['required', 'integer', 'min:0', 'max:100'];
        }

        return $rules;
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $validator->errors()->has('weights.*') && array_sum(array_map('intval', (array) $this->input('weights', []))) === 0) {
                    $validator->errors()->add('weights', __('At least one weight must be above zero.'));
                }
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function settingsData(): array
    {
        $data = $this->validated();

        return [
            ...$data,
            'weights' => array_map('intval', $data['weights']),
            'thresholds' => array_map('intval', $data['thresholds']),
        ];
    }
}
