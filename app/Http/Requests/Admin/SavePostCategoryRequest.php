<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\ValidatesTranslations;
use App\Models\PostCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SavePostCategoryRequest extends FormRequest
{
    use ValidatesTranslations;

    public function authorize(): bool
    {
        $category = $this->route('post_category');

        return $category instanceof PostCategory
            ? ($this->user()?->can('update', $category) ?? false)
            : ($this->user()?->can('create', PostCategory::class) ?? false);
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
        $category = $this->route('post_category');

        return [
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            ...$this->translationRules(
                ['name' => ['string', 'max:100'], 'description' => ['string', 'max:300']],
                'name',
                ['table' => 'post_category_translations', 'foreign_key' => 'post_category_id', 'ignore' => $category instanceof PostCategory ? $category->getKey() : null],
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
