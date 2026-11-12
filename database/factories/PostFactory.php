<?php

namespace Database\Factories;

use App\Enums\PostOrigin;
use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Published, human-written articles by default.
 *
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'author_id' => User::factory(),
            'status' => PostStatus::Published,
            'origin' => PostOrigin::Human,
            'is_featured' => false,
            'published_at' => now()->subDay(),
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => PostStatus::Draft, 'published_at' => null]);
    }

    public function inReview(): static
    {
        return $this->state(['status' => PostStatus::InReview, 'published_at' => null, 'submitted_at' => now()]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PostStatus::Approved,
            'published_at' => null,
            'approved_at' => now(),
            'reviewed_at' => now(),
            'reviewed_by' => User::factory(),
        ]);
    }

    public function scheduled(\DateTimeInterface $when): static
    {
        return $this->approved()->state(['status' => PostStatus::Scheduled, 'scheduled_for' => $when]);
    }

    public function aiOrigin(): static
    {
        return $this->state(['origin' => PostOrigin::Ai]);
    }

    /**
     * Add a translation for the locale (title, slug, excerpt and a small body with an H2 and an internal link).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function withTranslation(string $locale = 'en', array $attributes = []): static
    {
        return $this->afterCreating(function (Post $post) use ($locale, $attributes): void {
            $title = $attributes['title'] ?? Str::title(fake()->words(5, true));

            $post->translations()->create([
                'locale' => $locale,
                'title' => $title,
                'slug' => Str::slug($title).'-'.$post->id,
                'excerpt' => fake()->sentence(14),
                'body' => '<p>'.fake()->paragraph().'</p><h2>'.fake()->words(3, true).'</h2><p>'.fake()->paragraph().' <a href="/en/services">Services</a></p>',
                ...$attributes,
            ]);
        });
    }
}
