<?php

namespace App\Http\Requests;

use App\Enums\ContactTopic;
use App\Http\Requests\Concerns\GuardsAgainstSpam;
use App\Models\Language;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Public contact form, protected by the shared honeypot + time-trap checks.
 */
class ContactMessageRequest extends FormRequest
{
    use GuardsAgainstSpam;

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
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9 ()-]{6,30}$/'],
            'topic' => ['required', Rule::enum(ContactTopic::class)],
            'message' => ['required', 'string', 'min:10', 'max:2000'],
            'preferred_locale' => ['required', Rule::in(Language::active()->keys()->all())],
            'consent' => ['accepted'],
            self::TIMESTAMP => ['required', 'string'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->rejectExpiredForm($validator)];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('Full name'),
            'email' => __('Email'),
            'phone' => __('Phone'),
            'topic' => __('Subject'),
            'message' => __('Message'),
            'preferred_locale' => __('Preferred language'),
            'consent' => __('Consent'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'consent.accepted' => __('Please agree to the Privacy Policy so we can reply to you.'),
            self::TIMESTAMP.'.required' => __('This form has expired. Please review your message and send it again.'),
        ];
    }
}
