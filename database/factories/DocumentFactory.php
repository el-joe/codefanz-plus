<?php

namespace Database\Factories;

use App\Enums\ClientVisibility;
use App\Enums\DocumentCategory;
use App\Models\Document;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Document rows only; tests that download a file put it on the faked disk at `path`.
 *
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'project_id' => Project::factory(),
            'customer_id' => fn (array $attributes) => Project::query()->whereKey($attributes['project_id'])->value('customer_id'),
            'title' => fake()->words(2, true).'.pdf',
            'category' => DocumentCategory::Contract,
            'visibility' => ClientVisibility::Client,
            'version' => 1,
            'disk' => 'local',
            'path' => 'project-documents/'.Str::random(40).'.pdf',
            'original_name' => 'document.pdf',
            'mime' => 'application/pdf',
            'size' => 1024,
        ];
    }

    public function internal(): static
    {
        return $this->state(fn (): array => ['visibility' => ClientVisibility::Internal]);
    }
}
