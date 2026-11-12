<?php

namespace Database\Factories;

use App\Enums\AiGenerationStatus;
use App\Enums\PromptPurpose;
use App\Models\AiGeneration;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiGeneration>
 */
class AiGenerationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'purpose' => PromptPurpose::SocialPost,
            'mode' => 'write',
            'locales' => ['en'],
            'inputs' => ['topic' => 'Launch'],
            'output' => ['en' => ['text' => 'We launched.', 'hashtags' => '#launch', 'call_to_action' => 'Read more']],
            'guardrail_flags' => [],
            'provider' => 'openai',
            'model' => 'gpt-4.1-mini',
            'input_tokens' => 100,
            'output_tokens' => 50,
            'estimated_cost_minor' => 0.012,
            'status' => AiGenerationStatus::Generated,
            'is_dry_run' => false,
            'user_id' => User::factory(),
        ];
    }
}
