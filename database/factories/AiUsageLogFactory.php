<?php

namespace Database\Factories;

use App\Enums\AiFeature;
use App\Enums\AiUsageStatus;
use App\Models\AiUsageLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiUsageLog>
 */
class AiUsageLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'feature' => AiFeature::ProjectAssistant,
            'provider' => 'openai',
            'model' => 'gpt-4.1-mini',
            'input_tokens' => 1000,
            'output_tokens' => 200,
            'estimated_cost_minor' => 0.072,
            'currency' => 'USD',
            'latency_ms' => 900,
            'status' => AiUsageStatus::Succeeded,
            'created_at' => now(),
        ];
    }

    public function failed(): static
    {
        return $this->state(['status' => AiUsageStatus::Failed, 'input_tokens' => 0, 'output_tokens' => 0, 'estimated_cost_minor' => 0, 'error' => 'HTTP 500']);
    }
}
