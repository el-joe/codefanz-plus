<?php

namespace App\Http\Requests\Api\Mcp;

use App\Models\Language;
use App\Services\Mcp\McpContentTypes;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * propose_seo_meta: a new SEO title and/or meta description for one item in one language.
 */
class ProposeSeoMetaRequest extends FormRequest
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
        return [
            'type' => ['required', 'string', Rule::in(McpContentTypes::TYPES)],
            'id' => ['required', 'integer', 'min:1'],
            'locale' => ['required', 'string', Rule::in(Language::active()->keys()->all())],
            'seo_title' => ['required_without:seo_description', 'nullable', 'string', 'max:70'],
            'seo_description' => ['required_without:seo_title', 'nullable', 'string', 'max:170'],
            'summary' => ['nullable', 'string', 'max:255'],
            'expected_version' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
