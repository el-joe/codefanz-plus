<?php

namespace App\Actions\Blog;

use App\Models\Tag;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class SaveTag
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array{translations: array<string, array<string, string|null>>}  $data
     */
    public function handle(?Tag $tag, array $data, User $editor): Tag
    {
        return DB::transaction(function () use ($tag, $data, $editor): Tag {
            $before = $tag ? ['translations' => $tag->translationSnapshot(['name', 'slug'])] : null;

            if ($tag === null) {
                $tag = Tag::create();
            } else {
                $tag->touch();
            }

            $tag->syncTranslations($data['translations'], Tag::TRANSLATED_FIELDS, 'name');

            $this->audit->record(
                operation: $before === null ? 'tag.created' : 'tag.updated',
                auditable: $tag,
                user: $editor,
                before: $before,
                after: ['translations' => $tag->translationSnapshot(['name', 'slug'])],
            );

            return $tag;
        });
    }
}
