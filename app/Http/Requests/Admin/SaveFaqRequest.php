<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\ValidatesTranslations;
use App\Models\Faq;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveFaqRequest extends FormRequest
{
    use ValidatesTranslations;

    public function authorize(): bool
    {
        $faq = $this->route('faq');

        return $faq instanceof Faq
            ? ($this->user()?->can('update', $faq) ?? false)
            : ($this->user()?->can('create', Faq::class) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_featured' => $this->boolean('is_featured')]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'faq_category_id' => ['nullable', 'integer', Rule::exists('faq_categories', 'id')],
            'is_featured' => ['boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            ...$this->translationRules([
                'question' => ['string', 'max:255'],
                'answer' => ['string', 'max:5000'],
            ], 'question'),
        ];
    }
}
