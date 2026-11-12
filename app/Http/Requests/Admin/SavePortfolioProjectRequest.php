<?php

namespace App\Http\Requests\Admin;

use App\Enums\ContentStatus;
use App\Enums\ProjectType;
use App\Enums\ProjectVisibility;
use App\Http\Requests\Admin\Concerns\ValidatesLibraryImages;
use App\Http\Requests\Admin\Concerns\ValidatesTranslations;
use App\Models\Language;
use App\Models\PortfolioProject;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePortfolioProjectRequest extends FormRequest
{
    use ValidatesLibraryImages, ValidatesTranslations;

    public const MAX_KEY_FACTS = 12;

    public const MAX_GALLERY = 20;

    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project instanceof PortfolioProject
            ? ($this->user()?->can('update', $project) ?? false)
            : ($this->user()?->can('create', PortfolioProject::class) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->normaliseTranslatedSlugs();
        $this->merge([
            'is_featured' => $this->boolean('is_featured'),
            'is_client_public' => $this->boolean('is_client_public'),
            'key_facts' => array_values((array) $this->input('key_facts', [])),
            'gallery_media_ids' => $this->filledIds('gallery_media_ids'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $project = $this->route('project');
        $projectId = $project instanceof PortfolioProject ? $project->getKey() : null;
        $https = ['nullable', 'string', 'max:500', 'url:https'];

        $rules = [
            'lock_version' => [$projectId ? 'required' : 'nullable', 'integer'],
            'portfolio_category_id' => ['nullable', 'integer', Rule::exists('portfolio_categories', 'id')],
            'industry_id' => ['nullable', 'integer', Rule::exists('industries', 'id')->whereNull('deleted_at')],
            'type' => ['required', Rule::enum(ProjectType::class)],
            'visibility' => ['required', Rule::enum(ProjectVisibility::class)],
            'client_name' => ['nullable', 'string', 'max:150'],
            'is_client_public' => ['boolean'],
            'project_date' => ['nullable', 'date', 'after:1990-01-01', 'before_or_equal:'.now()->addYear()->toDateString()],
            'live_url' => $https,
            'android_url' => $https,
            'ios_url' => $https,
            'github_url' => $https,
            'video_url' => [...$https, function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && ! PortfolioProject::isAllowedVideoUrl($value)) {
                    $fail(__('Use a YouTube or Vimeo video link.'));
                }
            }],
            'is_featured' => ['boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            'cover_media_id' => ['nullable', 'integer', $this->libraryImageRule()],
            'gallery_media_ids' => ['array', 'max:'.self::MAX_GALLERY],
            'gallery_media_ids.*' => ['integer', 'distinct', $this->libraryImageRule()],
            'technology_ids' => ['nullable', 'array', 'max:60'],
            'technology_ids.*' => ['integer', 'distinct', Rule::exists('technologies', 'id')->whereNull('deleted_at')],
            'service_ids' => ['nullable', 'array', 'max:30'],
            'service_ids.*' => ['integer', 'distinct', Rule::exists('services', 'id')->where('status', ContentStatus::Published->value)->whereNull('deleted_at')],
            'key_facts' => ['array', 'max:'.self::MAX_KEY_FACTS],
            ...$this->translationRules([
                'title' => ['string', 'max:200'],
                'summary' => ['string', 'max:300'],
                'challenge' => ['string', 'max:10000'],
                'solution' => ['string', 'max:10000'],
                'results' => ['string', 'max:20000'],
                'seo_title' => ['string', 'max:70'],
                'seo_description' => ['string', 'max:170'],
            ], 'title', ['table' => 'portfolio_project_translations', 'foreign_key' => 'portfolio_project_id', 'ignore' => $projectId]),
        ];

        foreach (Language::active()->keys() as $code) {
            $rules["key_facts.*.translations.{$code}.label"] = ['nullable', 'string', 'max:60'];
            $rules["key_facts.*.translations.{$code}.value"] = ['nullable', 'string', 'max:120'];
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
            '*.url' => __('Enter a full https:// link.'),
        ];
    }
}
