<?php

namespace App\Http\Requests\Admin;

use App\Enums\Permission;
use App\Enums\RadarBudgetType;
use App\Models\Language;
use App\Models\RadarPlatform;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filter profile form. Keyword, skill and country lists arrive as comma- or line-separated text.
 */
class SaveRadarFilterRequest extends FormRequest
{
    private const LISTS = ['include_keywords', 'exclude_keywords', 'skills', 'countries'];

    public function authorize(): bool
    {
        return $this->user()?->can(Permission::RadarManage->value) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $lists = [];

        foreach (self::LISTS as $key) {
            $lists[$key] = array_values(array_unique(array_filter(array_map('trim', preg_split('/[\n,،]+/u', (string) $this->input($key, '')) ?: []), 'filled')));
        }

        $this->merge([
            ...$lists,
            'countries' => array_map('strtoupper', $lists['countries']),
            'platforms' => array_values((array) $this->input('platforms', [])),
            'languages' => array_values((array) $this->input('languages', [])),
            'is_active' => $this->boolean('is_active'),
            'alerts_enabled' => $this->boolean('alerts_enabled'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'include_keywords' => ['array', 'max:50'],
            'include_keywords.*' => ['string', 'max:60'],
            'exclude_keywords' => ['array', 'max:50'],
            'exclude_keywords.*' => ['string', 'max:60'],
            'skills' => ['array', 'max:50'],
            'skills.*' => ['string', 'max:60'],
            'countries' => ['array', 'max:60'],
            'countries.*' => ['string', 'size:2', 'alpha'],
            'platforms' => ['array'],
            'platforms.*' => ['string', Rule::in(RadarPlatform::query()->pluck('key')->all())],
            'languages' => ['array'],
            'languages.*' => ['string', Rule::in(Language::active()->keys()->all())],
            'min_budget' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'min_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'budget_type' => ['nullable', Rule::in([RadarBudgetType::Fixed->value, RadarBudgetType::Hourly->value])],
            'is_active' => ['boolean'],
            'alerts_enabled' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function filterData(): array
    {
        $data = $this->validated();

        return [
            'name' => $data['name'],
            'is_active' => $data['is_active'],
            'alerts_enabled' => $data['alerts_enabled'],
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'rules' => [
                'include_keywords' => $data['include_keywords'],
                'exclude_keywords' => $data['exclude_keywords'],
                'skills' => $data['skills'],
                'countries' => $data['countries'],
                'platforms' => $data['platforms'],
                'languages' => $data['languages'],
                'min_budget' => $data['min_budget'] ?? null,
                'min_score' => $data['min_score'] ?? null,
                'budget_type' => $data['budget_type'] ?? null,
            ],
        ];
    }
}
