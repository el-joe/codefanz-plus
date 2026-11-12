<?php

namespace App\Http\Requests\Assistant;

use App\Http\Requests\Concerns\GuardsAgainstSpam;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Starting a conversation: a honeypot and the encrypted page token (no minimum time, since people may
 * start right away). Bots are answered without a session being created.
 */
class StartAssistantRequest extends FormRequest
{
    use GuardsAgainstSpam;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            self::HONEYPOT => ['nullable'],
            self::TIMESTAMP => ['nullable', 'string'],
        ];
    }

    public function isBot(): bool
    {
        return filled($this->input(self::HONEYPOT)) || $this->renderedAt() === null;
    }

    public function isExpired(): bool
    {
        $renderedAt = $this->renderedAt();

        return $renderedAt !== null && now()->getTimestamp() - $renderedAt > config('codefanz.assistant.session_hours') * 3600;
    }
}
