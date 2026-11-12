<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\ValidatesTranslations;
use App\Models\Product;
use App\Models\ProductVersion;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveProductVersionRequest extends FormRequest
{
    use ValidatesTranslations;

    public const VERSION_PATTERN = '/^v?\d+(?:\.\d+){0,3}(?:-[0-9A-Za-z.]+)?$/';

    public function authorize(): bool
    {
        $product = $this->route('product');

        return $product instanceof Product && ($this->user()?->can('update', $product) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['version' => ltrim(trim((string) $this->input('version')), 'vV')]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $product = $this->route('product');
        $version = $this->route('version');

        return [
            'version' => [
                'required', 'string', 'max:30', 'regex:'.self::VERSION_PATTERN,
                Rule::unique('product_versions', 'version')
                    ->where('product_id', $product instanceof Product ? $product->getKey() : 0)
                    ->ignore($version instanceof ProductVersion ? $version->getKey() : null),
            ],
            'released_at' => ['nullable', 'date', 'before_or_equal:'.now()->addYear()->toDateString()],
            ...$this->translationRules(['changelog' => ['string', 'max:10000']], 'changelog'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['version.regex' => __('Use a version number such as 2.3 or 2.3.1-beta.')];
    }
}
