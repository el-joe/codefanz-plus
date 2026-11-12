<?php

namespace App\Http\Requests\Admin;

use App\Enums\TechnologyProficiency;
use App\Http\Requests\Admin\Concerns\ValidatesTranslations;
use App\Models\Technology;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveTechnologyRequest extends FormRequest
{
    use ValidatesTranslations;

    public const KEY_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    public function authorize(): bool
    {
        $technology = $this->route('technology');

        return $technology instanceof Technology
            ? ($this->user()?->can('update', $technology) ?? false)
            : ($this->user()?->can('create', Technology::class) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'key' => mb_strtolower(trim((string) $this->input('key'))),
            'is_visible' => $this->boolean('is_visible'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $technology = $this->route('technology');

        return [
            'technology_category_id' => ['required', 'integer', Rule::exists('technology_categories', 'id')],
            'key' => ['required', 'string', 'max:60', 'regex:'.self::KEY_PATTERN, Rule::unique('technologies', 'key')->ignore($technology instanceof Technology ? $technology->getKey() : null)],
            'icon' => ['nullable', 'string', 'max:60', 'regex:'.self::KEY_PATTERN],
            'website_url' => ['nullable', 'url:https,http', 'max:255'],
            // "Verified in projects" is computed from published portfolio projects and can never be set by hand.
            'proficiency' => ['required', Rule::in(array_map(fn (TechnologyProficiency $level) => $level->value, TechnologyProficiency::assignable()))],
            'is_visible' => ['boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            ...$this->translationRules([
                'name' => ['string', 'max:100'],
                'description' => ['string', 'max:500'],
            ], 'name'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'key.regex' => __('Use lowercase letters, numbers and single hyphens only.'),
            'proficiency.in' => __('Choose Supported or Expertise. Verified is calculated from published projects.'),
        ];
    }
}
