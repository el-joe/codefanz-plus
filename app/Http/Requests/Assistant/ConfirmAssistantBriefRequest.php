<?php

namespace App\Http\Requests\Assistant;

use App\Enums\ContactMethod;
use App\Models\Language;
use App\Services\Assistant\BriefNormalizer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Confirm & send: contact details (prefilled from the conversation, editable) and consent. Same rules as the quote form.
 */
class ConfirmAssistantBriefRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'required_if:preferred_contact,whatsapp,phone', 'string', 'max:30', 'regex:'.BriefNormalizer::PHONE_PATTERN],
            'company' => ['nullable', 'string', 'max:160'],
            'preferred_contact' => ['required', Rule::enum(ContactMethod::class)],
            'preferred_locale' => ['required', Rule::in(Language::active()->keys()->all())],
            'consent' => ['accepted'],
        ];
    }

    /**
     * @return array{name: string, email: string, phone: string|null, company: string|null, preferred_contact: string, preferred_locale: string}
     */
    public function contact(): array
    {
        $data = $this->validated();

        return [
            'name' => (string) BriefNormalizer::cleanText($data['name'], 120),
            'email' => mb_strtolower(trim($data['email'])),
            'phone' => filled($data['phone'] ?? null) ? trim($data['phone']) : null,
            'company' => BriefNormalizer::cleanText($data['company'] ?? null, 160),
            'preferred_contact' => $data['preferred_contact'],
            'preferred_locale' => $data['preferred_locale'],
        ];
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
            'company' => __('Company name'),
            'preferred_contact' => __('Preferred contact method'),
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
            'phone.required_if' => __('Add a phone number so we can reach you on WhatsApp or by phone.'),
        ];
    }
}
