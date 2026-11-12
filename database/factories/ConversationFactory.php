<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'customer_id' => fn (array $attributes) => Project::query()->whereKey($attributes['project_id'])->value('customer_id'),
            'subject' => null,
        ];
    }
}
