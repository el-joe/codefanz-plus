<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\Validator;

/**
 * Bot checks for public forms: a hidden honeypot field and an encrypted render timestamp (time trap).
 * Spam is answered like a normal submission by the controller so the checks are not easy to probe.
 */
trait GuardsAgainstSpam
{
    public const HONEYPOT = 'website';

    public const TIMESTAMP = 'form_token';

    /**
     * Encrypted render time placed in the form; tampering makes it undecryptable.
     */
    public static function timestampToken(): string
    {
        return Crypt::encryptString((string) now()->getTimestamp());
    }

    /**
     * A form older than the allowed window gets a visible error (a person may simply have left the tab open).
     */
    protected function rejectExpiredForm(Validator $validator): void
    {
        $renderedAt = $this->renderedAt();

        if ($renderedAt !== null && now()->getTimestamp() - $renderedAt > config('codefanz.contact_form.max_minutes') * 60) {
            $validator->errors()->add('form', __('This form has expired. Please review your message and send it again.'));
        }
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
}
