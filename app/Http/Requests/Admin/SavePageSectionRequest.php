<?php

namespace App\Http\Requests\Admin;

use App\Models\Language;
use App\Models\PageSection;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SavePageSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $section = $this->route('section');

        return $section instanceof PageSection && ($this->user()?->can('update', $section) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_visible' => $this->boolean('is_visible'),
            'items' => array_values(array_filter((array) $this->input('items', []), 'is_array')),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var PageSection $section */
        $section = $this->route('section');
        $limit = $section->type->limit();

        $rules = [
            'is_visible' => ['boolean'],
            'limit' => $limit ? ['nullable', 'integer', 'min:1', 'max:'.$limit['max']] : ['prohibited'],
            'cta_url' => $section->type->hasCustomCtaUrl()
                ? ['nullable', 'string', 'max:500', function (string $attribute, mixed $value, Closure $fail): void {
                    $isHttps = parse_url((string) $value, PHP_URL_SCHEME) === 'https' && filter_var($value, FILTER_VALIDATE_URL) !== false;
                    $isSitePath = preg_match('#^/(?!/)[^\s]*$#', (string) $value) === 1;

                    if (! $isHttps && ! $isSitePath) {
                        $fail(__('Use a full https:// link or a site path starting with /.'));
                    }
                }]
                : ['prohibited'],
            'translations' => ['required', 'array'],
            'items' => ['array', 'max:8'],
        ];

        foreach (Language::active()->keys() as $code) {
            $rules["translations.{$code}.eyebrow"] = ['nullable', 'string', 'max:80'];
            $rules["translations.{$code}.heading"] = ['nullable', 'string', 'max:200'];
            $rules["translations.{$code}.subheading"] = ['nullable', 'string', 'max:500'];
            $rules["translations.{$code}.cta_label"] = ['nullable', 'string', 'max:60'];
            $rules["translations.{$code}.points"] = ['nullable', 'string', 'max:700'];
            $rules["items.*.translations.{$code}.title"] = ['nullable', 'string', 'max:80'];
            $rules["items.*.translations.{$code}.text"] = ['nullable', 'string', 'max:300'];
        }

        return $rules;
    }
}
