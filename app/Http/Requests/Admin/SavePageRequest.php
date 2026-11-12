<?php

namespace App\Http\Requests\Admin;

use App\Models\Language;
use App\Models\Page;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePageRequest extends FormRequest
{
    public const SLUG_PATTERN = '/^[\p{L}\p{N}]+(?:-[\p{L}\p{N}]+)*$/u';

    public function authorize(): bool
    {
        $page = $this->route('page');

        return $page instanceof Page
            ? ($this->user()?->can('update', $page) ?? false)
            : ($this->user()?->can('create', Page::class) ?? false);
    }

    /**
     * Normalise slugs to lowercase before validation.
     */
    protected function prepareForValidation(): void
    {
        $translations = (array) $this->input('translations', []);

        foreach ($translations as $code => $fields) {
            if (isset($fields['slug']) && is_string($fields['slug'])) {
                $translations[$code]['slug'] = mb_strtolower(trim($fields['slug']));
            }
        }

        $this->merge(['translations' => $translations]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $page = $this->route('page');
        $default = Language::defaultCode();

        $rules = [
            'template' => ['required', Rule::in(['default', 'legal'])],
            'lock_version' => [$page instanceof Page ? 'required' : 'nullable', 'integer'],
            'translations' => ['required', 'array'],
        ];

        foreach (Language::active()->keys() as $code) {
            $required = $code === $default ? 'required' : 'nullable';

            $rules["translations.{$code}.title"] = [$required, 'string', 'max:200'];
            $rules["translations.{$code}.slug"] = [
                $required,
                "required_with:translations.{$code}.title",
                'string',
                'max:200',
                'regex:'.self::SLUG_PATTERN,
                Rule::unique('page_translations', 'slug')
                    ->where('locale', $code)
                    ->when($page instanceof Page, fn ($rule) => $rule->whereNot('page_id', $page->getKey())),
            ];
            $rules["translations.{$code}.body"] = ['nullable', 'string', 'max:50000'];
            $rules["translations.{$code}.seo_title"] = ['nullable', 'string', 'max:70'];
            $rules["translations.{$code}.seo_description"] = ['nullable', 'string', 'max:170'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'translations.*.slug.regex' => __('Use lowercase letters, numbers and single hyphens only.'),
        ];
    }
}
