<?php

namespace App\Http\Requests\Admin\Concerns;

use App\Http\Requests\Admin\SavePageRequest;
use App\Models\Language;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared rules for `translations[{locale}][{field}]` inputs (one card per active language).
 *
 * @mixin FormRequest
 */
trait ValidatesTranslations
{
    /**
     * Lowercase and trim every submitted slug before validation.
     */
    protected function normaliseTranslatedSlugs(): void
    {
        $translations = (array) $this->input('translations', []);

        foreach ($translations as $code => $fields) {
            if (is_array($fields) && isset($fields['slug']) && is_string($fields['slug'])) {
                $translations[$code]['slug'] = mb_strtolower(trim($fields['slug']));
            }
        }

        $this->merge(['translations' => $translations]);
    }

    /**
     * Build rules for every active language. The required field is mandatory for the default language only.
     * When $slug is given, a `slug` field is validated and kept unique per locale.
     *
     * @param  array<string, list<mixed>>  $fields  field => rules (without required/nullable)
     * @param  array{table: string, foreign_key: string, ignore: int|null}|null  $slug
     * @return array<string, list<mixed>>
     */
    protected function translationRules(array $fields, string $requiredField, ?array $slug = null): array
    {
        $default = Language::defaultCode();
        $rules = ['translations' => ['required', 'array']];

        foreach (Language::active()->keys() as $code) {
            foreach ($fields as $field => $fieldRules) {
                $presence = $field === $requiredField && $code === $default ? 'required' : 'nullable';
                $rules["translations.{$code}.{$field}"] = [$presence, ...$fieldRules];
            }

            if ($slug !== null) {
                $rules["translations.{$code}.slug"] = [
                    $code === $default ? 'required' : 'nullable',
                    "required_with:translations.{$code}.{$requiredField}",
                    'string',
                    'max:200',
                    'regex:'.SavePageRequest::SLUG_PATTERN,
                    Rule::unique($slug['table'], 'slug')
                        ->where('locale', $code)
                        ->when($slug['ignore'] !== null, fn ($rule) => $rule->whereNot($slug['foreign_key'], $slug['ignore'])),
                ];
            }
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    protected function translationMessages(): array
    {
        return [
            'translations.*.slug.regex' => __('Use lowercase letters, numbers and single hyphens only.'),
        ];
    }
}
