<?php

namespace App\Actions\Seo;

use App\Models\Redirect;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Seo\RedirectPaths;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates or updates a managed redirect. Sources and targets are normalized; self-redirects, loops and chains
 * (the target is itself redirected, or another redirect already points at this source) are rejected so every
 * redirect resolves in one hop.
 */
class SaveRedirect
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array{from_path: string, to_path?: ?string, status_code: int, is_active?: bool, note?: ?string}  $data
     */
    public function handle(?Redirect $redirect, array $data, User $editor, bool $audit = true): Redirect
    {
        [$from, $to, $code] = $this->normalize($data);

        return DB::transaction(function () use ($redirect, $data, $editor, $from, $to, $code, $audit): Redirect {
            $duplicate = Redirect::query()->where('from_path', $from)->when($redirect, fn ($query) => $query->whereKeyNot($redirect->getKey()))->exists();

            if ($duplicate) {
                throw ValidationException::withMessages(['from_path' => __('A redirect from this path already exists.')]);
            }

            $isActive = (bool) ($data['is_active'] ?? true);

            if ($isActive) {
                $this->guardChains($redirect, $from, $to);
            }

            $before = $redirect?->only(['from_path', 'to_path', 'status_code', 'is_active']);
            $redirect ??= new Redirect(['created_by' => $editor->getKey()]);
            $redirect->fill([
                'from_path' => $from,
                'to_path' => $to,
                'status_code' => $code,
                'is_active' => $isActive,
                'note' => $data['note'] ?? null,
                'updated_by' => $editor->getKey(),
            ])->save();

            if ($audit) {
                $this->audit->record(
                    operation: $before === null ? 'redirect.created' : 'redirect.updated',
                    auditable: $redirect,
                    user: $editor,
                    before: $before,
                    after: $redirect->only(['from_path', 'to_path', 'status_code', 'is_active']),
                );
            }

            return $redirect;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: string, 1: ?string, 2: int}
     */
    public function normalize(array $data): array
    {
        $code = (int) $data['status_code'];
        $from = RedirectPaths::source((string) $data['from_path']);

        if ($from === null || $from === '/') {
            throw ValidationException::withMessages(['from_path' => __('Enter a path on this site, such as /en/old-page.')]);
        }

        if ($code === 410) {
            return [$from, null, $code];
        }

        $to = RedirectPaths::target((string) ($data['to_path'] ?? ''));

        if ($to === null || trim((string) ($data['to_path'] ?? '')) === '') {
            throw ValidationException::withMessages(['to_path' => __('Enter a path on this site or an https:// URL.')]);
        }

        if (RedirectPaths::targetPath($to) === $from) {
            throw ValidationException::withMessages(['to_path' => __('A page cannot redirect to itself.')]);
        }

        return [$from, $to, $code];
    }

    private function guardChains(?Redirect $redirect, string $from, ?string $to): void
    {
        $others = Redirect::query()->where('is_active', true)->when($redirect, fn ($query) => $query->whereKeyNot($redirect->getKey()));
        $targetPath = RedirectPaths::targetPath($to);

        if ($targetPath !== null) {
            $next = (clone $others)->where('from_path', $targetPath)->first();

            if ($next !== null) {
                throw ValidationException::withMessages(['to_path' => $next->to_path !== null && RedirectPaths::targetPath($next->to_path) === $from
                    ? __('This would create a redirect loop with :path.', ['path' => $next->from_path])
                    : __('The target is itself redirected to :target. Point directly to the final URL to avoid a redirect chain.', ['target' => $next->to_path ?? '410'])]);
            }
        }

        $pointing = (clone $others)->where(fn ($query) => $query->where('to_path', $from)->orWhere('to_path', 'like', addcslashes($from, '\\%_').'?%'))->first();

        if ($pointing !== null) {
            throw ValidationException::withMessages(['from_path' => __(':path already redirects here. Change that redirect to the final URL instead of creating a chain.', ['path' => $pointing->from_path])]);
        }
    }
}
