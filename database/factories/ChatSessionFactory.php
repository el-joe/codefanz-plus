<?php

namespace Database\Factories;

use App\Enums\ChatMode;
use App\Enums\ChatSessionStatus;
use App\Models\ChatSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChatSession>
 */
class ChatSessionFactory extends Factory
{
    /**
     * Token every factory session accepts (tests send it as X-Assistant-Token).
     */
    public const TOKEN = 'test-assistant-token';

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'token_hash' => hash('sha256', self::TOKEN),
            'locale' => 'en',
            'mode' => ChatMode::Ai,
            'status' => ChatSessionStatus::Active,
            'brief' => [],
            'user_turns' => 0,
            'ip_hash' => hash('sha256', '127.0.0.1'),
            'last_activity_at' => now(),
        ];
    }

    public function guided(): static
    {
        return $this->state(['mode' => ChatMode::Guided, 'fallback_reason' => 'not_configured']);
    }
}
