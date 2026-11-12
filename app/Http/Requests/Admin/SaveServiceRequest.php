<?php

namespace App\Http\Requests\Admin;

use App\Enums\ContentIcon;
use App\Enums\MediaStatus;
use App\Enums\ServiceTier;
use App\Http\Requests\Admin\Concerns\ValidatesTranslations;
use App\Models\Language;
use App\Models\Service;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveServiceRequest extends FormRequest
{
    use ValidatesTranslations;

    public const MAX_CHILD_ROWS = 30;

    public function authorize(): bool
    {
        $service = $this->route('service');

        return $service instanceof Service
            ? ($this->user()?->can('update', $service) ?? false)
            : ($this->user()?->can('create', Service::class) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->normaliseTranslatedSlugs();
        $this->merge([
            'is_featured' => $this->boolean('is_featured'),
            'features' => array_values((array) $this->input('features', [])),
            'faqs' => array_values((array) $this->input('faqs', [])),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $service = $this->route('service');
        $serviceId = $service instanceof Service ? $service->getKey() : null;

        $rules = [
            'lock_version' => [$serviceId ? 'required' : 'nullable', 'integer'],
            'tier' => ['required', Rule::enum(ServiceTier::class)],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('services', 'id')->whereNull('parent_id')->whereNull('deleted_at'),
                Rule::notIn(array_filter([$serviceId])),
            ],
            'icon' => ['nullable', Rule::enum(ContentIcon::class)],
            'is_featured' => ['boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            'cover_media_id' => [
                'nullable',
                'integer',
                Rule::exists('media', 'id')
                    ->where('status', MediaStatus::Ready->value)
                    ->where(fn ($query) => $query->where('mime_type', 'like', 'image/%'))
                    ->whereNull('deleted_at'),
            ],
            'technology_ids' => ['nullable', 'array', 'max:60'],
            'technology_ids.*' => ['integer', 'distinct', Rule::exists('technologies', 'id')->whereNull('deleted_at')],
            'features' => ['array', 'max:'.self::MAX_CHILD_ROWS],
            'features.*.id' => ['nullable', 'integer', Rule::exists('service_features', 'id')->where('service_id', $serviceId ?? 0)],
            'faqs' => ['array', 'max:'.self::MAX_CHILD_ROWS],
            'faqs.*.id' => ['nullable', 'integer', Rule::exists('service_faqs', 'id')->where('service_id', $serviceId ?? 0)],
            ...$this->translationRules([
                'title' => ['string', 'max:200'],
                'summary' => ['string', 'max:300'],
                'body' => ['string', 'max:50000'],
                'seo_title' => ['string', 'max:70'],
                'seo_description' => ['string', 'max:170'],
            ], 'title', ['table' => 'service_translations', 'foreign_key' => 'service_id', 'ignore' => $serviceId]),
        ];

        foreach (Language::active()->keys() as $code) {
            $rules["features.*.translations.{$code}.title"] = ['nullable', 'string', 'max:200'];
            $rules["features.*.translations.{$code}.description"] = ['nullable', 'string', 'max:500'];
            $rules["faqs.*.translations.{$code}.question"] = ['nullable', 'string', 'max:300'];
            $rules["faqs.*.translations.{$code}.answer"] = ['nullable', 'string', 'max:5000'];
        }

        return $rules;
    }

    /**
     * A service with sub-services cannot itself become a sub-service (one level of nesting).
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $service = $this->route('service');

                if ($service instanceof Service && $this->filled('parent_id') && $service->children()->exists()) {
                    $validator->errors()->add('parent_id', __('This service has sub-services, so it cannot be placed under another service.'));
                }
            },
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
