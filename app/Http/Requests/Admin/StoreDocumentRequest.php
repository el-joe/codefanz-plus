<?php

namespace App\Http\Requests\Admin;

use App\Enums\ClientVisibility;
use App\Enums\DocumentCategory;
use App\Http\Requests\Concerns\ValidatesUploadedFiles;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentRequest extends FormRequest
{
    use ValidatesUploadedFiles;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('project')) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', ...$this->uploadedFileRules()],
            'title' => ['nullable', 'string', 'max:160'],
            'category' => ['required', Rule::enum(DocumentCategory::class)],
            'visibility' => ['required', Rule::enum(ClientVisibility::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['file' => __('File')];
    }
}
