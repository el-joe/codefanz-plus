<?php

namespace App\Http\Requests\Admin;

use App\Enums\Permission;
use App\Enums\PromptPurpose;
use App\Models\PromptTemplate;
use App\Services\Ai\PromptTemplateValidator;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SavePromptTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::AiManage->value) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $variables = array_values(array_filter(
            (array) $this->input('variables', []),
            fn ($variable): bool => is_array($variable) && filled($variable['name'] ?? null),
        ));

        $this->merge([
            'key' => mb_strtolower(trim((string) $this->input('key'))),
            'variables' => array_map(fn (array $variable): array => [
                'name' => trim((string) $variable['name']),
                'label' => trim((string) ($variable['label'] ?? '')),
                'required' => (bool) ($variable['required'] ?? false),
                'max' => (int) ($variable['max'] ?? 0) ?: 2000,
            ], $variables),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var PromptTemplate|null $template */
        $template = $this->route('template');

        return [
            'key' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/', Rule::unique('prompt_templates', 'key')->ignore($template?->id)],
            'name' => ['required', 'string', 'max:120'],
            'purpose' => ['required', Rule::enum(PromptPurpose::class)],
            'locale' => ['nullable', 'string', Rule::exists('languages', 'code')->where('is_active', true)],
            'description' => ['nullable', 'string', 'max:255'],
            'system_template' => ['required', 'string', 'max:8000'],
            'user_template' => ['required', 'string', 'max:8000'],
            'variables' => ['array', 'max:12'],
            'variables.*.name' => ['required', 'string', 'max:40'],
            'variables.*.label' => ['nullable', 'string', 'max:120'],
            'variables.*.required' => ['boolean'],
            'variables.*.max' => ['integer', 'min:1', 'max:20000'],
            'is_active' => ['boolean'],
            'lock_version' => [$template !== null ? 'required' : 'nullable', 'integer'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $errors = app(PromptTemplateValidator::class)->errors(
                    (string) $this->input('system_template'),
                    (string) $this->input('user_template'),
                    (array) $this->input('variables', []),
                );

                foreach ($errors as $field => $message) {
                    $validator->errors()->add($field, $message);
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'key' => __('Key'),
            'name' => __('Name'),
            'purpose' => __('Purpose'),
            'system_template' => __('System prompt'),
            'user_template' => __('User prompt'),
        ];
    }
}
