<?php

namespace App\Actions\Content;

use App\Models\Service;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Direct edits by authorized editors. Each save bumps the version, so any pending AI/MCP proposal becomes stale.
 */
class SaveService
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array{tier: string, parent_id?: int|null, icon?: string|null, is_featured?: bool, sort_order: int, cover_media_id?: int|null, technology_ids?: list<int>|null, lock_version?: int|null, translations: array<string, array<string, string|null>>, features?: list<array{id?: int|null, translations?: array<string, array<string, string|null>>}>, faqs?: list<array{id?: int|null, translations?: array<string, array<string, string|null>>}>}  $data
     */
    public function handle(?Service $service, array $data, User $editor): Service
    {
        return DB::transaction(function () use ($service, $data, $editor): Service {
            $attributes = [
                'parent_id' => $data['parent_id'] ?? null,
                'tier' => $data['tier'],
                'icon' => $data['icon'] ?? null,
                'is_featured' => (bool) ($data['is_featured'] ?? false),
                'sort_order' => (int) $data['sort_order'],
                'updated_by' => $editor->getKey(),
            ];

            if ($service === null) {
                $service = Service::create([...$attributes, 'created_by' => $editor->getKey()]);
                $before = null;
            } else {
                $service = Service::query()->lockForUpdate()->findOrFail($service->getKey());

                if ((int) ($data['lock_version'] ?? 0) !== $service->approvableVersion()) {
                    throw ValidationException::withMessages([
                        'lock_version' => __('Someone else saved this service while you were editing. Reload to see their changes.'),
                    ]);
                }

                $before = $this->snapshot($service);
                $service->fill($attributes);
                $service->lock_version++;
                $service->save();
            }

            $service->syncTranslations($data['translations'], Service::TRANSLATED_FIELDS, 'title');
            $this->syncChildRows($service->features(), $data['features'] ?? [], ['title', 'description'], 'title');
            $this->syncChildRows($service->faqs(), $data['faqs'] ?? [], ['question', 'answer'], 'question');
            $service->technologies()->sync($data['technology_ids'] ?? []);
            $service->syncMediaCollection('cover', array_filter([(int) ($data['cover_media_id'] ?? 0)]));

            $this->audit->record(
                operation: $before === null ? 'service.created' : 'service.updated',
                auditable: $service,
                user: $editor,
                before: $before,
                after: $this->snapshot($service),
            );

            return $service;
        });
    }

    /**
     * Replace ordered child rows: submitted rows are kept in submission order, missing or empty rows are removed.
     *
     * @param  HasMany<Model, Service>  $relation
     * @param  list<array{id?: int|null, translations?: array<string, array<string, string|null>>}>  $rows
     * @param  list<string>  $fields
     */
    private function syncChildRows(HasMany $relation, array $rows, array $fields, string $requiredField): void
    {
        $keep = [];
        $position = 0;

        foreach ($rows as $row) {
            $translations = $row['translations'] ?? [];

            if (collect($translations)->every(fn (array $values): bool => blank($values[$requiredField] ?? null))) {
                continue;
            }

            $child = filled($row['id'] ?? null)
                ? $relation->getRelated()->newQuery()->whereKey($row['id'])->where('service_id', $relation->getParentKey())->firstOrFail()
                : $relation->getRelated()->newInstance(['service_id' => $relation->getParentKey()]);

            $child->forceFill(['sort_order' => $position++])->save();
            $child->syncTranslations($translations, $fields, $requiredField);
            $keep[] = $child->getKey();
        }

        $relation->getRelated()->newQuery()
            ->where('service_id', $relation->getParentKey())
            ->whereNotIn('id', $keep)
            ->delete();
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Service $service): array
    {
        return [
            'parent_id' => $service->parent_id,
            'tier' => $service->tier->value,
            'icon' => $service->icon,
            'is_featured' => $service->is_featured,
            'sort_order' => $service->sort_order,
            'version' => $service->lock_version,
            'translations' => $service->translationSnapshot(['title', 'slug', 'summary', 'seo_title', 'seo_description']),
            'features' => $service->features()->count(),
            'faqs' => $service->faqs()->count(),
            'technology_ids' => $service->technologies()->pluck('technologies.id')->all(),
            'cover_media_id' => $service->cover()->value('media.id'),
        ];
    }
}
