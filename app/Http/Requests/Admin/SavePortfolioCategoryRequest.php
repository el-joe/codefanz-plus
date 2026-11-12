<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\ValidatesTranslations;
use App\Models\PortfolioCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SavePortfolioCategoryRequest extends FormRequest
{
    use ValidatesTranslations;

    public function authorize(): bool
    {
        $category = $this->route('portfolio_category');

        return $category instanceof PortfolioCategory
            ? ($this->user()?->can('update', $category) ?? false)
            : ($this->user()?->can('create', PortfolioCategory::class) ?? false);
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
        $category = $this->route('portfolio_category');

        return [
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            ...$this->translationRules(
                ['name' => ['string', 'max:100']],
                'name',
                ['table' => 'portfolio_category_translations', 'foreign_key' => 'portfolio_category_id', 'ignore' => $category instanceof PortfolioCategory ? $category->getKey() : null],
            ),
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
