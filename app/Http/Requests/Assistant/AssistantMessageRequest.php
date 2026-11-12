<?php

namespace App\Http\Requests\Assistant;

use App\Services\Assistant\BriefNormalizer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AssistantMessageRequest extends FormRequest
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
            'message' => ['required', 'string', 'max:'.config('codefanz.assistant.max_message_length')],
        ];
    }

    /**
     * The visitor's message as plain text (tags stripped, whitespace collapsed).
     */
    public function text(): string
    {
        return (string) BriefNormalizer::cleanText($this->validated('message'), (int) config('codefanz.assistant.max_message_length'));
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['message' => __('Message')];
    }
}
