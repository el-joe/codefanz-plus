<?php

namespace Database\Factories;

use App\Enums\MediaStatus;
use App\Models\Media;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Media>
 */
class MediaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $uuid = (string) Str::uuid();

        return [
            'uuid' => $uuid,
            'original_disk' => 'local',
            'original_path' => "media/originals/{$uuid}.jpg",
            'original_name' => fake()->word().'.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 120_000,
            'checksum' => hash('sha256', $uuid),
            'width' => 1600,
            'height' => 900,
            'alt' => ['en' => fake()->sentence(4)],
            'status' => MediaStatus::Ready,
        ];
    }
}
