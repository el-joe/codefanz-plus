<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\ValidatesTranslations;
use App\Models\FaqCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveFaqCategoryRequest extends FormRequest
{
    use ValidatesTranslations;

    public function authorize(): bool
    {
        $category = $this->route('faq_category');

        return $category instanceof FaqCategory
            ? ($this->user()?->can('update', $category) ?? false)
            : ($this->user()?->can('create', FaqCategory::class) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            ...$this->translationRules(['name' => ['string', 'max:100']], 'name'),
        ];
    }
}
