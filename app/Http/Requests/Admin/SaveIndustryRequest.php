<?php

namespace App\Http\Requests\Admin;

use App\Enums\ContentIcon;
use App\Http\Requests\Admin\Concerns\ValidatesTranslations;
use App\Models\Industry;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveIndustryRequest extends FormRequest
{
    use ValidatesTranslations;

    public function authorize(): bool
    {
        $industry = $this->route('industry');

        return $industry instanceof Industry
            ? ($this->user()?->can('update', $industry) ?? false)
            : ($this->user()?->can('create', Industry::class) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->normaliseTranslatedSlugs();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $industry = $this->route('industry');

        return [
            'icon' => ['nullable', Rule::enum(ContentIcon::class)],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            ...$this->translationRules([
                'name' => ['string', 'max:150'],
                'summary' => ['string', 'max:500'],
                'typical_solutions' => ['string', 'max:1000'],
                'seo_title' => ['string', 'max:70'],
                'seo_description' => ['string', 'max:170'],
            ], 'name', ['table' => 'industry_translations', 'foreign_key' => 'industry_id', 'ignore' => $industry instanceof Industry ? $industry->getKey() : null]),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->translationMessages();
    }
}
