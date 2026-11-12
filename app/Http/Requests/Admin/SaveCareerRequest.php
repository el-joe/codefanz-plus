<?php

namespace App\Http\Requests\Admin;

use App\Enums\EmploymentType;
use App\Http\Requests\Admin\Concerns\ValidatesTranslations;
use App\Models\Career;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCareerRequest extends FormRequest
{
    use ValidatesTranslations;

    public function authorize(): bool
    {
        $career = $this->route('career');

        return $career instanceof Career
            ? ($this->user()?->can('update', $career) ?? false)
            : ($this->user()?->can('create', Career::class) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->normaliseTranslatedSlugs();
        $this->merge(['is_remote' => $this->boolean('is_remote')]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $career = $this->route('career');

        return [
            'employment_type' => ['required', Rule::enum(EmploymentType::class)],
            'is_remote' => ['boolean'],
            'closes_at' => ['nullable', 'date', 'before_or_equal:'.now()->addYears(2)->toDateString()],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            ...$this->translationRules([
                'title' => ['string', 'max:150'],
                'department' => ['string', 'max:100'],
                'location' => ['string', 'max:150'],
                'summary' => ['string', 'max:300'],
                'responsibilities' => ['string', 'max:3000'],
                'requirements' => ['string', 'max:3000'],
                'body' => ['string', 'max:10000'],
                'seo_title' => ['string', 'max:70'],
                'seo_description' => ['string', 'max:170'],
            ], 'title', ['table' => 'career_translations', 'foreign_key' => 'career_id', 'ignore' => $career instanceof Career ? $career->getKey() : null]),
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
