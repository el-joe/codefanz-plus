<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UseGenerationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('use', $this->route('generation')) ?? false;
    }

    /**
     * Edited output: outputs[locale][field]. Unknown locales or fields are ignored by UseGeneration.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'outputs' => ['nullable', 'array'],
            'outputs.*' => ['array'],
            'outputs.*.*' => ['nullable', 'string', 'max:20000'],
        ];
    }

    /**
     * @return array<string, array<string, string|null>>
     */
    public function edited(): array
    {
        return (array) $this->validated('outputs', []);
    }
}
