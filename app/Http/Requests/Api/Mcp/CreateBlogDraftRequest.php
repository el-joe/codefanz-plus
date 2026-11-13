<?php

namespace App\Http\Requests\Api\Mcp;

use App\Models\Language;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * create_blog_draft: one or more languages, each with a title and a Markdown body.
 */
class CreateBlogDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $locales = Language::active()->keys()->all();

        return [
            'translations' => ['required', 'array', 'min:1', 'array:'.implode(',', $locales)],
            'translations.*.title' => ['required', 'string', 'max:160'],
            'translations.*.excerpt' => ['nullable', 'string', 'max:500'],
            'translations.*.body' => ['required', 'string', 'max:50000'],
            'translations.*.seo_title' => ['nullable', 'string', 'max:70'],
            'translations.*.seo_description' => ['nullable', 'string', 'max:170'],
            'translations.*.focus_keyword' => ['nullable', 'string', 'max:100'],
            'post_category_id' => ['nullable', 'integer', Rule::exists('post_categories', 'id')],
        ];
    }
}
