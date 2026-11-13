<?php

namespace App\Http\Requests\Api\Mcp;

use App\Models\Language;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * propose_content_change: field => new value for one language. Which fields are allowed depends on the target
 * (its approvable attributes) and is checked against the loaded item in the controller.
 */
class ProposeContentChangeRequest extends FormRequest
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
            'locale' => ['required', 'string', Rule::in(Language::active()->keys()->all())],
            'changes' => ['required', 'array', 'min:1', 'max:10'],
            'changes.*' => ['nullable', 'string', 'max:50000'],
            'summary' => ['nullable', 'string', 'max:255'],
            'expected_version' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
