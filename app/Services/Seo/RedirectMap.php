<?php

namespace App\Services\Seo;

use App\Models\Redirect;
use Illuminate\Support\Facades\Cache;

/**
 * Active redirects as one cached plain array: from_path => [id, to, code]. Flushed on any redirect write.
 */
class RedirectMap
{
    private const CACHE_KEY = 'seo:redirect-map';

    /**
     * @return array<string, array{id: int, to: string|null, code: int}>
     */
    public function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn (): array => Redirect::query()
            ->where('is_active', true)
            ->get(['id', 'from_path', 'to_path', 'status_code'])
            ->mapWithKeys(fn (Redirect $redirect): array => [$redirect->from_path => ['id' => $redirect->id, 'to' => $redirect->to_path, 'code' => $redirect->status_code]])
            ->all());
    }

    /**
     * @return array{id: int, to: string|null, code: int}|null
     */
    public function find(string $path): ?array
    {
        return $this->all()[$path] ?? null;
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
