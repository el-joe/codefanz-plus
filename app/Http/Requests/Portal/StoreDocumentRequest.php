<?php

namespace App\Http\Requests\Portal;

use App\Enums\DocumentCategory;
use App\Http\Requests\Concerns\ValidatesUploadedFiles;
use App\Models\Project;
use App\Services\CurrentCustomer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A client shares a file with the team. The project must belong to the current customer; the file is always client-visible.
 */
class StoreDocumentRequest extends FormRequest
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
            'project' => ['required', 'string', Rule::exists('projects', 'ulid')->where('customer_id', app(CurrentCustomer::class)->id())->whereNull('deleted_at')],
            'file' => ['required', ...$this->uploadedFileRules()],
            'title' => ['nullable', 'string', 'max:160'],
            'category' => ['required', Rule::enum(DocumentCategory::class)],
        ];
    }

    public function project(): Project
    {
        return Project::query()->forCurrentCustomer()->where('ulid', $this->validated('project'))->firstOrFail();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['file' => __('File'), 'project' => __('Project')];
    }
}
