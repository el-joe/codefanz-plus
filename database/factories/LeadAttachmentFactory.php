<?php

namespace Database\Factories;

use App\Models\Lead;
use App\Models\LeadAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LeadAttachment>
 */
class LeadAttachmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'lead_id' => Lead::factory(),
            'disk' => config('codefanz.media.private_disk'),
            'path' => 'lead-attachments/'.Str::random(40).'.pdf',
            'original_name' => 'brief.pdf',
            'mime' => 'application/pdf',
            'size' => 1024,
        ];
    }
}
