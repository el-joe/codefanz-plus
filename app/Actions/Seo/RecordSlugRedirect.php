<?php

namespace App\Actions\Seo;

use App\Models\Redirect;
use App\Services\AuditLogger;
use App\Services\Seo\RedirectMap;
use App\Services\Seo\RedirectPaths;
use App\Services\Seo\SeoTargets;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * When a published item's slug changes, the old URL gets an automatic 301 to the new one. Existing redirects to
 * the old URL are re-pointed (no chains) and any redirect from the new URL is removed (it is live again).
 */
class RecordSlugRedirect
{
    public function __construct(private SeoTargets $targets, private AuditLogger $audit) {}

    public function fromTranslation(Model $translation): void
    {
        if (! $translation->wasChanged('slug')) {
            return;
        }

        $parentClass = Str::beforeLast($translation::class, 'Translation');
        $type = $this->typeForClass($parentClass);
        $oldSlug = (string) $translation->getOriginal('slug');

        if ($type === null || $oldSlug === '' || $oldSlug === $translation->slug) {
            return;
        }

        $parent = $parentClass::query()->find($translation->{Str::snake(class_basename($parentClass)).'_id'});

        if ($parent === null || ! $this->targets->isPublic($parent)) {
            return;
        }

        $this->record(
            RedirectPaths::source($this->targets->path($type, $oldSlug, $translation->locale)),
            RedirectPaths::source($this->targets->path($type, (string) $translation->slug, $translation->locale)),
            $parent,
        );
    }

    public function record(?string $from, ?string $to, Model $parent): void
    {
        if ($from === null || $to === null || $from === $to) {
            return;
        }

        DB::transaction(function () use ($from, $to, $parent): void {
            Redirect::query()->where('from_path', $to)->delete();
            Redirect::query()->where('to_path', $from)->update(['to_path' => $to, 'updated_at' => now()]);

            $redirect = Redirect::query()->updateOrCreate(
                ['from_path' => $from],
                ['to_path' => $to, 'status_code' => 301, 'is_active' => true, 'is_automatic' => true, 'note' => __('Created automatically when the slug changed.')],
            );

            $this->audit->record(
                operation: 'redirect.auto_created',
                auditable: $redirect,
                user: auth()->user(),
                after: ['from_path' => $from, 'to_path' => $to, 'for' => $parent->getMorphClass().':'.$parent->getKey()],
            );
        });

        RedirectMap::flush();
    }

    private function typeForClass(string $class): ?string
    {
        foreach ($this->targets->types() as $type) {
            if ($this->targets->model($type) === $class) {
                return $type;
            }
        }

        return null;
    }
}
