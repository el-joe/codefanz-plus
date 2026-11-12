<?php

namespace Database\Factories;

use App\Enums\PromptPurpose;
use App\Models\PromptTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PromptTemplate>
 */
class PromptTemplateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(2),
            'name' => 'Blog article',
            'purpose' => PromptPurpose::BlogDraft,
            'locale' => null,
            'description' => 'Long-form article',
            'system_template' => "You write for {{brand_name}}.\nVoice: {{brand_voice}}\nGlossary:\n{{glossary}}",
            'user_template' => "Write about {{topic}}.\nNotes: {{notes}}",
            'variables' => [
                ['name' => 'topic', 'label' => 'Topic', 'required' => true, 'max' => 300],
                ['name' => 'notes', 'label' => 'Notes', 'required' => false, 'max' => 2000],
            ],
            'version' => 1,
            'is_active' => true,
        ];
    }

    public function purpose(PromptPurpose $purpose): static
    {
        return $this->state(fn (): array => [
            'purpose' => $purpose,
            'name' => $purpose->label(),
            'user_template' => 'Improve this. Goal: {{goal}}',
            'variables' => [['name' => 'goal', 'label' => 'Goal', 'required' => false, 'max' => 500]],
        ]);
    }
}
