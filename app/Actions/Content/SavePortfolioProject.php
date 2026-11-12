<?php

namespace App\Actions\Content;

use App\Models\Language;
use App\Models\PortfolioProject;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Direct edits by authorized editors. Each save bumps the version, so any pending AI/MCP proposal becomes stale.
 */
class SavePortfolioProject
{
    public const KEY_FACT_FIELDS = ['label', 'value'];

    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array{portfolio_category_id?: int|null, industry_id?: int|null, type: string, client_name?: string|null, is_client_public?: bool, project_date?: string|null, live_url?: string|null, android_url?: string|null, ios_url?: string|null, github_url?: string|null, video_url?: string|null, is_featured?: bool, visibility: string, sort_order: int, cover_media_id?: int|null, gallery_media_ids?: list<int|null>|null, technology_ids?: list<int>|null, service_ids?: list<int>|null, lock_version?: int|null, translations: array<string, array<string, string|null>>, key_facts?: list<array{translations?: array<string, array<string, string|null>>}>}  $data
     */
    public function handle(?PortfolioProject $project, array $data, User $editor): PortfolioProject
    {
        return DB::transaction(function () use ($project, $data, $editor): PortfolioProject {
            $attributes = [
                'portfolio_category_id' => $data['portfolio_category_id'] ?? null,
                'industry_id' => $data['industry_id'] ?? null,
                'type' => $data['type'],
                'client_name' => $data['client_name'] ?? null,
                'is_client_public' => (bool) ($data['is_client_public'] ?? false),
                'project_date' => $data['project_date'] ?? null,
                'live_url' => $data['live_url'] ?? null,
                'android_url' => $data['android_url'] ?? null,
                'ios_url' => $data['ios_url'] ?? null,
                'github_url' => $data['github_url'] ?? null,
                'video_url' => $data['video_url'] ?? null,
                'is_featured' => (bool) ($data['is_featured'] ?? false),
                'visibility' => $data['visibility'],
                'sort_order' => (int) $data['sort_order'],
                'updated_by' => $editor->getKey(),
            ];

            if ($project === null) {
                $project = PortfolioProject::create([...$attributes, 'created_by' => $editor->getKey()]);
                $before = null;
            } else {
                $project = PortfolioProject::query()->lockForUpdate()->findOrFail($project->getKey());

                if ((int) ($data['lock_version'] ?? 0) !== $project->approvableVersion()) {
                    throw ValidationException::withMessages([
                        'lock_version' => __('Someone else saved this project while you were editing. Reload to see their changes.'),
                    ]);
                }

                $before = $this->snapshot($project);
                $project->fill($attributes);
                $project->lock_version++;
                $project->save();
            }

            $locales = Language::active()->keys()->all();
            $keyFacts = TranslatedList::fromRows($data['key_facts'] ?? [], self::KEY_FACT_FIELDS, 'label', $locales);
            $translations = collect($data['translations'])
                ->map(fn (array $fields, string $locale): array => [...$fields, 'key_facts' => $keyFacts[$locale] ?? null])
                ->all();

            $project->syncTranslations($translations, PortfolioProject::TRANSLATED_FIELDS, 'title');
            $project->technologies()->sync($data['technology_ids'] ?? []);
            $project->services()->sync($data['service_ids'] ?? []);
            $project->syncMediaCollection('cover', array_filter([(int) ($data['cover_media_id'] ?? 0)]));
            $project->syncMediaCollection('gallery', array_values(array_unique(array_filter(array_map('intval', $data['gallery_media_ids'] ?? [])))));

            $this->audit->record(
                operation: $before === null ? 'portfolio_project.created' : 'portfolio_project.updated',
                auditable: $project,
                user: $editor,
                before: $before,
                after: $this->snapshot($project),
            );

            return $project;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(PortfolioProject $project): array
    {
        return [
            ...$project->only(['portfolio_category_id', 'industry_id', 'client_name', 'is_client_public', 'live_url', 'android_url', 'ios_url', 'github_url', 'video_url', 'is_featured', 'sort_order']),
            'type' => $project->type->value,
            'visibility' => $project->visibility->value,
            'project_date' => $project->project_date?->toDateString(),
            'version' => $project->lock_version,
            'translations' => $project->translationSnapshot(['title', 'slug', 'summary', 'seo_title', 'seo_description']),
            'technology_ids' => $project->technologies()->pluck('technologies.id')->all(),
            'service_ids' => $project->services()->pluck('services.id')->all(),
            'cover_media_id' => $project->cover()->value('media.id'),
            'gallery_media_ids' => $project->gallery()->pluck('media.id')->all(),
        ];
    }
}
