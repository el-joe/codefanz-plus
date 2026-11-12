<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\ValidatesTranslations;
use App\Models\Tag;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveTagRequest extends FormRequest
{
    use ValidatesTranslations;

    public function authorize(): bool
    {
        $tag = $this->route('tag');

        return $tag instanceof Tag
            ? ($this->user()?->can('update', $tag) ?? false)
            : ($this->user()?->can('create', Tag::class) ?? false);
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
        $tag = $this->route('tag');

        return $this->translationRules(
            ['name' => ['string', 'max:100']],
            'name',
            ['table' => 'tag_translations', 'foreign_key' => 'tag_id', 'ignore' => $tag instanceof Tag ? $tag->getKey() : null],
        );
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->translationMessages();
    }
}
