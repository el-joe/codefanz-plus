<?php

namespace App\Actions\Content;

use App\Enums\ContentStatus;
use App\Models\Testimonial;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Saves a testimonial. Withdrawing the client's consent immediately takes a published testimonial offline.
 */
class SaveTestimonial
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array{portfolio_project_id?: int|null, author_name: string, company?: string|null, rating?: int|null, consent_confirmed?: bool, is_featured?: bool, sort_order: int, avatar_media_id?: int|null, translations: array<string, array<string, string|null>>}  $data
     */
    public function handle(?Testimonial $testimonial, array $data, User $editor): Testimonial
    {
        return DB::transaction(function () use ($testimonial, $data, $editor): Testimonial {
            $consent = (bool) ($data['consent_confirmed'] ?? false);
            $before = $testimonial ? $this->snapshot($testimonial) : null;

            $attributes = [
                'portfolio_project_id' => $data['portfolio_project_id'] ?? null,
                'author_name' => $data['author_name'],
                'company' => $data['company'] ?? null,
                'rating' => $data['rating'] ?? null,
                'is_featured' => (bool) ($data['is_featured'] ?? false),
                'sort_order' => (int) $data['sort_order'],
                'updated_by' => $editor->getKey(),
            ];

            $testimonial ??= new Testimonial(['created_by' => $editor->getKey()]);
            $testimonial->fill($attributes);

            if ($consent && ! $testimonial->consent_confirmed) {
                $testimonial->forceFill(['consent_confirmed' => true, 'consent_confirmed_at' => now(), 'consent_confirmed_by' => $editor->getKey()]);
            } elseif (! $consent) {
                $testimonial->forceFill(['consent_confirmed' => false, 'consent_confirmed_at' => null, 'consent_confirmed_by' => null]);

                if ($testimonial->status === ContentStatus::Published) {
                    $testimonial->status = ContentStatus::Draft;
                }
            }

            $testimonial->save();
            $testimonial->syncTranslations($data['translations'], Testimonial::TRANSLATED_FIELDS, 'quote');
            $testimonial->syncMediaCollection('avatar', array_filter([(int) ($data['avatar_media_id'] ?? 0)]));

            $this->audit->record(
                operation: $before === null ? 'testimonial.created' : 'testimonial.updated',
                auditable: $testimonial,
                user: $editor,
                before: $before,
                after: $this->snapshot($testimonial),
            );

            return $testimonial;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Testimonial $testimonial): array
    {
        return [
            ...$testimonial->only(['portfolio_project_id', 'author_name', 'company', 'rating', 'consent_confirmed', 'is_featured', 'sort_order']),
            'status' => $testimonial->status->value,
            'translations' => $testimonial->translationSnapshot(Testimonial::TRANSLATED_FIELDS),
            'avatar_media_id' => $testimonial->avatar()->value('media.id'),
        ];
    }
}
