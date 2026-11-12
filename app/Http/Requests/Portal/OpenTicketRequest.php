<?php

namespace App\Http\Requests\Portal;

use App\Enums\TicketCategory;
use App\Enums\TicketPriority;
use App\Models\Project;
use App\Services\CurrentCustomer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OpenTicketRequest extends FormRequest
{
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
            'subject' => ['required', 'string', 'max:160'],
            'category' => ['required', Rule::enum(TicketCategory::class)],
            'priority' => ['required', Rule::enum(TicketPriority::class)],
            'project' => ['nullable', 'string', Rule::exists('projects', 'ulid')->where('customer_id', app(CurrentCustomer::class)->id())->whereNull('deleted_at')],
            'body' => ['required', 'string', 'max:'.config('codefanz.support.reply_max_length')],
        ];
    }

    /**
     * @return array{subject: string, category: string, priority: string, project_id: int|null, body: string}
     */
    public function ticketData(): array
    {
        $project = $this->filled('project') ? Project::query()->forCurrentCustomer()->where('ulid', $this->validated('project'))->first() : null;

        return [
            'subject' => $this->validated('subject'),
            'category' => $this->validated('category'),
            'priority' => $this->validated('priority'),
            'project_id' => $project?->getKey(),
            'body' => $this->validated('body'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['subject' => __('Subject'), 'body' => __('Describe the issue'), 'project' => __('Project')];
    }
}
