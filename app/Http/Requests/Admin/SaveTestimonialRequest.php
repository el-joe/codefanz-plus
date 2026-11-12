<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\ValidatesLibraryImages;
use App\Http\Requests\Admin\Concerns\ValidatesTranslations;
use App\Models\Testimonial;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveTestimonialRequest extends FormRequest
{
    use ValidatesLibraryImages, ValidatesTranslations;

    public function authorize(): bool
    {
        $testimonial = $this->route('testimonial');

        return $testimonial instanceof Testimonial
            ? ($this->user()?->can('update', $testimonial) ?? false)
            : ($this->user()?->can('create', Testimonial::class) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'consent_confirmed' => $this->boolean('consent_confirmed'),
            'is_featured' => $this->boolean('is_featured'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'author_name' => ['required', 'string', 'max:120'],
            'company' => ['nullable', 'string', 'max:150'],
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'consent_confirmed' => ['boolean'],
            'is_featured' => ['boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            'portfolio_project_id' => ['nullable', 'integer', Rule::exists('portfolio_projects', 'id')->whereNull('deleted_at')],
            'avatar_media_id' => ['nullable', 'integer', $this->libraryImageRule()],
            ...$this->translationRules([
                'quote' => ['string', 'max:600'],
                'author_role' => ['string', 'max:150'],
            ], 'quote'),
        ];
    }
}
