<?php

namespace App\Actions\Content;

use App\Enums\PageStatus;
use App\Models\Page;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Direct edits by authorized editors. Each save bumps the version, so any pending AI/MCP proposal becomes stale.
 */
class SavePage
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array{template: string, lock_version?: int|null, translations: array<string, array<string, string|null>>}  $data
     */
    public function handle(?Page $page, array $data, User $editor): Page
    {
        return DB::transaction(function () use ($page, $data, $editor): Page {
            $isNew = $page === null;

            if ($isNew) {
                $page = Page::create(['template' => $data['template'], 'status' => PageStatus::Draft, 'created_by' => $editor->getKey(), 'updated_by' => $editor->getKey()]);
                $before = null;
            } else {
                $page = Page::query()->lockForUpdate()->findOrFail($page->getKey());

                if ((int) ($data['lock_version'] ?? 0) !== $page->approvableVersion()) {
                    throw ValidationException::withMessages([
                        'lock_version' => __('Someone else saved this page while you were editing. Reload to see their changes.'),
                    ]);
                }

                $before = $this->snapshot($page);
                $page->fill(['template' => $data['template'], 'updated_by' => $editor->getKey()]);
                $page->lock_version++;
                $page->save();
            }

            foreach ($data['translations'] as $locale => $fields) {
                if (blank($fields['title'] ?? null)) {
                    $page->translations()->where('locale', $locale)->delete();

                    continue;
                }

                $page->translations()->updateOrCreate(['locale' => $locale], [
                    'title' => $fields['title'],
                    'slug' => $fields['slug'],
                    'body' => $fields['body'] ?? null,
                    'seo_title' => $fields['seo_title'] ?? null,
                    'seo_description' => $fields['seo_description'] ?? null,
                ]);
            }

            $this->audit->record(
                operation: $isNew ? 'page.created' : 'page.updated',
                auditable: $page,
                user: $editor,
                before: $before,
                after: $this->snapshot($page),
            );

            return $page;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Page $page): array
    {
        return [
            'template' => $page->template,
            'version' => $page->lock_version,
            'translations' => $page->translations()->get(['locale', 'title', 'slug', 'seo_title', 'seo_description'])->keyBy('locale')->toArray(),
        ];
    }
}
