<?php

namespace App\Http\Requests\Admin;

use App\Enums\ContentIcon;
use App\Enums\PricingModel;
use App\Enums\ProductAvailability;
use App\Http\Requests\Admin\Concerns\ValidatesLibraryImages;
use App\Http\Requests\Admin\Concerns\ValidatesTranslations;
use App\Models\Language;
use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveProductRequest extends FormRequest
{
    use ValidatesLibraryImages, ValidatesTranslations;

    public const MAX_FEATURES = 24;

    public const MAX_SCREENSHOTS = 20;

    public function authorize(): bool
    {
        $product = $this->route('product');

        return $product instanceof Product
            ? ($this->user()?->can('update', $product) ?? false)
            : ($this->user()?->can('create', Product::class) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->normaliseTranslatedSlugs();
        $this->merge([
            'demo_enabled' => $this->boolean('demo_enabled'),
            'features' => array_values((array) $this->input('features', [])),
            'screenshot_media_ids' => $this->filledIds('screenshot_media_ids'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $product = $this->route('product');
        $productId = $product instanceof Product ? $product->getKey() : null;

        $rules = [
            'lock_version' => [$productId ? 'required' : 'nullable', 'integer'],
            'icon' => ['nullable', Rule::enum(ContentIcon::class)],
            'pricing_models' => ['required', 'array', 'min:1', 'max:'.count(PricingModel::cases())],
            'pricing_models.*' => ['distinct', Rule::enum(PricingModel::class)],
            'availability' => ['required', Rule::enum(ProductAvailability::class)],
            'demo_enabled' => ['boolean'],
            'docs_url' => ['nullable', 'string', 'max:500', 'url:https'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            'cover_media_id' => ['nullable', 'integer', $this->libraryImageRule()],
            'screenshot_media_ids' => ['array', 'max:'.self::MAX_SCREENSHOTS],
            'screenshot_media_ids.*' => ['integer', 'distinct', $this->libraryImageRule()],
            'technology_ids' => ['nullable', 'array', 'max:60'],
            'technology_ids.*' => ['integer', 'distinct', Rule::exists('technologies', 'id')->whereNull('deleted_at')],
            'features' => ['array', 'max:'.self::MAX_FEATURES],
            ...$this->translationRules([
                'name' => ['string', 'max:150'],
                'tagline' => ['string', 'max:200'],
                'description' => ['string', 'max:20000'],
                'licensing_notes' => ['string', 'max:2000'],
                'seo_title' => ['string', 'max:70'],
                'seo_description' => ['string', 'max:170'],
            ], 'name', ['table' => 'product_translations', 'foreign_key' => 'product_id', 'ignore' => $productId]),
        ];

        foreach (Language::active()->keys() as $code) {
            $rules["features.*.translations.{$code}.title"] = ['nullable', 'string', 'max:120'];
            $rules["features.*.translations.{$code}.description"] = ['nullable', 'string', 'max:300'];
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
            'docs_url.url' => __('Enter a full https:// link.'),
        ];
    }
}
