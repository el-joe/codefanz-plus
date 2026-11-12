<?php

namespace Database\Factories;

use App\Enums\ChangeAction;
use App\Enums\ChangeOrigin;
use App\Enums\ChangeRequestStatus;
use App\Enums\Permission;
use App\Models\ChangeRequest;
use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChangeRequest>
 */
class ChangeRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => 'CR-'.now()->format('Y').'-'.fake()->unique()->numerify('####'),
            'origin' => ChangeOrigin::Ai,
            'actor_label' => 'content-studio',
            'action' => ChangeAction::Update,
            'target_type' => (new Page)->getMorphClass(),
            'target_id' => Page::factory(),
            'target_version' => 1,
            'locale' => 'en',
            'before' => ['seo_title' => 'Old title'],
            'proposed' => ['seo_title' => 'New title'],
            'summary' => 'Update SEO title',
            'required_permission' => Permission::SeoApprove,
            'status' => ChangeRequestStatus::Pending,
            'expires_at' => now()->addDays(14),
        ];
    }
}
