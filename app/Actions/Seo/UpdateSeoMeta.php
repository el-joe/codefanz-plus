<?php

namespace App\Actions\Seo;

use App\Models\Post;
use App\Models\SeoMeta;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Blog\PostRevisions;
use App\Services\Seo\SeoTargets;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The SEO editor's narrow write path: SEO fields only (title, description, focus keyword, canonical, noindex) for
 * one item in one language. Optimistic locking uses the entity's lock_version (so pending AI proposals become
 * stale, like any other save), or the seo_meta row's own version for fixed site pages. Every write is audited.
 */
class UpdateSeoMeta
{
    public function __construct(private SeoTargets $targets, private AuditLogger $audit, private PostRevisions $revisions) {}

    /**
     * @param  array{type: string, target: string, locale: string, version: int, seo_title?: ?string, seo_description?: ?string, focus_keyword?: ?string, canonical_url?: ?string, noindex?: bool}  $data
     */
    public function handle(array $data, User $editor): void
    {
        $data['seo_title'] = $this->clean($data['seo_title'] ?? null);
        $data['seo_description'] = $this->clean($data['seo_description'] ?? null);
        $data['focus_keyword'] = $this->clean($data['focus_keyword'] ?? null);
        $data['canonical_url'] = $this->clean($data['canonical_url'] ?? null);
        $data['noindex'] = (bool) ($data['noindex'] ?? false);

        DB::transaction(fn () => $data['type'] === 'route'
            ? $this->updateRoute($data, $editor)
            : $this->updateEntity($data, $editor));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function updateRoute(array $data, User $editor): void
    {
        abort_unless($this->targets->isStaticRoute($data['target']), 404);

        $meta = SeoMeta::query()->where('route_key', $data['target'])->where('locale', $data['locale'])->lockForUpdate()->first();

        $this->guardVersion((int) $data['version'], $meta?->lock_version ?? 0);

        $before = $meta?->only(['title', 'description', 'focus_keyword', 'canonical_url', 'noindex']);
        $meta ??= new SeoMeta(['route_key' => $data['target'], 'locale' => $data['locale']]);
        $meta->fill([
            'title' => $data['seo_title'],
            'description' => $data['seo_description'],
            'focus_keyword' => $data['focus_keyword'],
            'canonical_url' => $data['canonical_url'],
            'noindex' => $data['noindex'],
            'updated_by' => $editor->getKey(),
        ]);
        $meta->lock_version = $meta->exists ? $meta->lock_version + 1 : 1;
        $meta->save();

        $this->audit->record(
            operation: 'seo.meta_updated',
            auditable: $meta,
            user: $editor,
            before: $before,
            after: ['route' => $data['target'], 'locale' => $data['locale'], ...$meta->only(['title', 'description', 'focus_keyword', 'canonical_url', 'noindex'])],
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function updateEntity(array $data, User $editor): void
    {
        abort_unless($this->targets->has($data['type']), 404);

        $modelClass = $this->targets->model($data['type']);
        /** @var Model $record */
        $record = $modelClass::query()->lockForUpdate()->findOrFail($data['target']);

        $this->guardVersion((int) $data['version'], (int) $record->lock_version);

        $translation = $record->translations()->where('locale', $data['locale'])->first();

        if ($translation === null) {
            throw ValidationException::withMessages(['locale' => __('This item has no content in that language yet.')]);
        }

        $isPost = $record instanceof Post;
        $meta = SeoMeta::query()->whereMorphedTo('seoable', $record)->where('locale', $data['locale'])->first();
        $before = [
            'seo_title' => $translation->seo_title,
            'seo_description' => $translation->seo_description,
            'focus_keyword' => $isPost ? $translation->focus_keyword : $meta?->focus_keyword,
            'canonical_url' => $isPost ? $translation->canonical_url : $meta?->canonical_url,
            'noindex' => (bool) $meta?->noindex,
        ];

        $translation->update([
            'seo_title' => $data['seo_title'],
            'seo_description' => $data['seo_description'],
            ...($isPost ? ['focus_keyword' => $data['focus_keyword'], 'canonical_url' => $data['canonical_url']] : []),
        ]);

        $metaFields = [
            'focus_keyword' => $isPost ? null : $data['focus_keyword'],
            'canonical_url' => $isPost ? null : $data['canonical_url'],
            'noindex' => $data['noindex'],
        ];

        if ($metaFields['focus_keyword'] === null && $metaFields['canonical_url'] === null && ! $metaFields['noindex']) {
            $meta?->delete();
        } else {
            $meta ??= new SeoMeta(['seoable_type' => $record->getMorphClass(), 'seoable_id' => $record->getKey(), 'locale' => $data['locale']]);
            $meta->fill([...$metaFields, 'updated_by' => $editor->getKey()]);
            $meta->lock_version = $meta->exists ? $meta->lock_version + 1 : 1;
            $meta->save();
        }

        $record->forceFill(['lock_version' => $record->lock_version + 1])->save();

        if ($record instanceof Post) {
            $record->unsetRelation('translations');
            $this->revisions->record($record, $editor, 'saved');
        }

        $this->audit->record(
            operation: 'seo.meta_updated',
            auditable: $record,
            user: $editor,
            before: ['locale' => $data['locale'], ...$before],
            after: [
                'locale' => $data['locale'],
                'seo_title' => $data['seo_title'],
                'seo_description' => $data['seo_description'],
                'focus_keyword' => $data['focus_keyword'],
                'canonical_url' => $data['canonical_url'],
                'noindex' => $data['noindex'],
                'version' => $record->lock_version,
            ],
        );
    }

    private function guardVersion(int $submitted, int $current): void
    {
        if ($submitted !== $current) {
            throw ValidationException::withMessages([
                'version' => __('Someone else saved this item while you were editing. Reload to see their changes.'),
            ]);
        }
    }

    private function clean(?string $value): ?string
    {
        $value = $value === null ? null : trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        return $value === '' ? null : $value;
    }
}
