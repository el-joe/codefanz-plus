<?php

namespace App\Http\Requests\Admin;

use App\Enums\MediaStatus;
use App\Http\Requests\Admin\Concerns\ValidatesTranslations;
use App\Models\Language;
use App\Models\Post;
use App\Services\Blog\EditorialChecklist;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePostRequest extends FormRequest
{
    use ValidatesTranslations;

    public const MAX_BODY = 200000;

    public function authorize(): bool
    {
        $post = $this->route('post');

        return $post instanceof Post
            ? ($this->user()?->can('update', $post) ?? false)
            : ($this->user()?->can('create', Post::class) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->normaliseTranslatedSlugs();

        $checklist = [];

        foreach (array_keys(EditorialChecklist::manualItems()) as $item) {
            $checklist[$item] = filter_var($this->input("checklist.{$item}", false), FILTER_VALIDATE_BOOLEAN);
        }

        $this->merge([
            'is_featured' => $this->boolean('is_featured'),
            'is_single_language' => $this->boolean('is_single_language'),
            'checklist' => $checklist,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $post = $this->route('post');
        $postId = $post instanceof Post ? $post->getKey() : null;

        $rules = [
            'lock_version' => [$postId ? 'required' : 'nullable', 'integer'],
            'post_category_id' => ['nullable', 'integer', Rule::exists('post_categories', 'id')],
            'tag_ids' => ['nullable', 'array', 'max:20'],
            'tag_ids.*' => ['integer', 'distinct', Rule::exists('tags', 'id')],
            'is_featured' => ['boolean'],
            'is_single_language' => ['boolean'],
            'cover_media_id' => [
                'nullable',
                'integer',
                Rule::exists('media', 'id')
                    ->where('status', MediaStatus::Ready->value)
                    ->where(fn ($query) => $query->where('mime_type', 'like', 'image/%'))
                    ->whereNull('deleted_at'),
            ],
            'checklist' => ['array'],
            'checklist.*' => ['boolean'],
            ...$this->translationRules([
                'title' => ['string', 'max:200'],
                'excerpt' => ['string', 'max:400'],
                'body' => ['string', 'max:'.self::MAX_BODY],
                'seo_title' => ['string', 'max:70'],
                'seo_description' => ['string', 'max:170'],
                'focus_keyword' => ['string', 'max:100'],
                'canonical_url' => ['string', 'max:500', 'url:https'],
            ], 'title', ['table' => 'post_translations', 'foreign_key' => 'post_id', 'ignore' => $postId]),
        ];

        foreach (Language::active()->keys() as $code) {
            $rules["translations.{$code}.slug"][] = Rule::notIn(Post::RESERVED_SLUGS);
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->translationMessages(),
            'translations.*.slug.not_in' => __('This URL slug is reserved. Choose another one.'),
            'translations.*.canonical_url.url' => __('Use a full https:// address.'),
        ];
    }
}
