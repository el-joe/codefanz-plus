<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\ValidatesUploadedFiles;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Message in a project conversation (admin and portal). Plain text; one optional file.
 * Authorization happens in the controllers (policy for staff, customer scope for clients).
 */
class StoreConversationMessageRequest extends FormRequest
{
    use ValidatesUploadedFiles;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'subject' => ['sometimes', 'required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:'.config('codefanz.projects.message_max_length')],
            'attachment' => ['nullable', ...$this->uploadedFileRules()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['body' => __('Message'), 'attachment' => __('Attachment'), 'subject' => __('Subject')];
    }
}
