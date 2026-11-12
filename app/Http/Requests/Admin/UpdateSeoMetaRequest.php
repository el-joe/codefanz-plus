<?php

namespace App\Http\Requests\Admin;

use App\Enums\Permission;
use App\Models\Language;
use App\Services\Seo\SeoTargets;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSeoMetaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::SeoManage->value) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $targets = app(SeoTargets::class);

        return [
            'type' => ['required', 'string', Rule::in(['route', ...$targets->types()])],
            'target' => ['required', 'string', 'max:60', $this->input('type') === 'route' ? Rule::in(array_keys($targets->staticRoutes())) : 'regex:/^\d+$/'],
            'locale' => ['required', 'string', Rule::in(Language::active()->keys()->all())],
            'version' => ['required', 'integer', 'min:0'],
            'seo_title' => ['nullable', 'string', 'max:70'],
            'seo_description' => ['nullable', 'string', 'max:170'],
            'focus_keyword' => ['nullable', 'string', 'max:100'],
            'canonical_url' => ['nullable', 'string', 'max:500', 'url:http,https'],
            'noindex' => ['nullable', 'boolean'],
            'row_key' => ['nullable', 'string', 'max:120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'seo_title' => __('SEO title'),
            'seo_description' => __('Meta description'),
            'focus_keyword' => __('Focus keyword'),
            'canonical_url' => __('Canonical URL'),
        ];
    }
}
