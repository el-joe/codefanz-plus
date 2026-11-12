<?php

namespace App\Http\Requests\Assistant;

use App\Enums\BudgetRange;
use App\Enums\ProjectPlatform;
use App\Enums\ProjectTimeline;
use App\Services\Assistant\BriefProgress;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * One step of the guided questionnaire (the no-AI fallback). Same enums and limits as the quote form.
 */
class AssistantAnswerRequest extends FormRequest
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
        $step = ['step' => ['required', 'string', Rule::in(BriefProgress::GUIDED_STEPS)]];

        return $step + match ($this->input('step')) {
            'project' => [
                'project_type' => ['required', 'string', 'max:120'],
                'description' => ['required', 'string', 'min:10', 'max:2000'],
                'services' => ['nullable', 'array', 'max:10'],
                'services.*' => ['integer', Rule::exists('services', 'id')->where('status', 'published')->whereNull('deleted_at')],
            ],
            'platforms' => [
                'platforms' => ['required', 'array', 'min:1'],
                'platforms.*' => ['distinct', Rule::enum(ProjectPlatform::class)],
            ],
            'features' => ['features' => ['required', 'string', 'min:3', 'max:2000']],
            'timeline' => ['timeline' => ['required', Rule::enum(ProjectTimeline::class)]],
            'budget' => ['budget_range' => ['nullable', Rule::enum(BudgetRange::class)]],
            default => [],
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function answers(): array
    {
        return collect($this->validated())->except('step')->all();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'project_type' => __('Project name'),
            'description' => __('Project description'),
            'services' => __('Services'),
            'platforms' => __('Platforms'),
            'platforms.*' => __('Platform'),
            'features' => __('Key features'),
            'timeline' => __('Timeline'),
            'budget_range' => __('Estimated budget'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['platforms.required' => __('Choose at least one platform.')];
    }
}
