<?php

namespace App\Http\Requests;

use App\Enums\ContactTopic;
use App\Models\Language;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Public contact form. Bot checks: a hidden honeypot field and an encrypted render timestamp (time trap).
 */
class ContactMessageRequest extends FormRequest
{
    public const HONEYPOT = 'website';

    public const TIMESTAMP = 'form_token';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Encrypted render time placed in the form; tampering makes it undecryptable.
     */
    public static function timestampToken(): string
    {
        return Crypt::encryptString((string) now()->getTimestamp());
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
     * A form older than the allowed window gets a visible error (a person may simply have left the tab open).
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $renderedAt = $this->renderedAt();

                if ($renderedAt !== null && now()->getTimestamp() - $renderedAt > config('codefanz.contact_form.max_minutes') * 60) {
                    $validator->errors()->add('form', __('This form has expired. Please review your message and send it again.'));
                }
            },
        ];
    }

    /**
     * Honeypot filled, timestamp missing or tampered, or submitted faster than a person could type.
     */
    public function isLikelySpam(): bool
    {
        $renderedAt = $this->renderedAt();

        return filled($this->input(self::HONEYPOT))
            || $renderedAt === null
            || now()->getTimestamp() - $renderedAt < config('codefanz.contact_form.min_seconds');
    }

    private function renderedAt(): ?int
    {
        try {
            $value = Crypt::decryptString((string) $this->input(self::TIMESTAMP));
        } catch (DecryptException) {
            return null;
        }

        return ctype_digit($value) ? (int) $value : null;
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
