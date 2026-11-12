<?php

namespace App\Http\Requests\Portal;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReviewMilestoneRequest extends FormRequest
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
            'decision' => ['required', 'in:approve,changes'],
            'feedback' => ['required_if:decision,changes', 'nullable', 'string', 'max:2000'],
        ];
    }

    public function approves(): bool
    {
        return $this->validated('decision') === 'approve';
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['feedback' => __('What should change?')];
    }
}
