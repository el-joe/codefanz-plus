<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\ValidatesTranslations;
use App\Models\TechnologyCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveTechnologyCategoryRequest extends FormRequest
{
    use ValidatesTranslations;

    public function authorize(): bool
    {
        $category = $this->route('technology_category');

        return $category instanceof TechnologyCategory
            ? ($this->user()?->can('update', $category) ?? false)
            : ($this->user()?->can('create', TechnologyCategory::class) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['key' => mb_strtolower(trim((string) $this->input('key')))]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $category = $this->route('technology_category');

        return [
            'key' => ['required', 'string', 'max:60', 'regex:'.SaveTechnologyRequest::KEY_PATTERN, Rule::unique('technology_categories', 'key')->ignore($category instanceof TechnologyCategory ? $category->getKey() : null)],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            ...$this->translationRules(['name' => ['string', 'max:100']], 'name'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['key.regex' => __('Use lowercase letters, numbers and single hyphens only.')];
    }
}
