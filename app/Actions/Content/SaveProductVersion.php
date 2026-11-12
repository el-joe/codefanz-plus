<?php

namespace App\Actions\Content;

use App\Models\Product;
use App\Models\ProductVersion;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class SaveProductVersion
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array{version: string, released_at?: string|null, translations: array<string, array<string, string|null>>}  $data
     */
    public function handle(Product $product, ?ProductVersion $version, array $data, User $editor): ProductVersion
    {
        return DB::transaction(function () use ($product, $version, $data, $editor): ProductVersion {
            $attributes = ['version' => $data['version'], 'released_at' => $data['released_at'] ?? null];
            $before = $version ? $this->snapshot($version) : null;

            $version = $version === null ? $product->versions()->create($attributes) : tap($version)->update($attributes);
            $version->syncTranslations($data['translations'], ProductVersion::TRANSLATED_FIELDS, 'changelog');

            $this->audit->record(
                operation: $before === null ? 'product_version.created' : 'product_version.updated',
                auditable: $version,
                user: $editor,
                before: $before,
                after: $this->snapshot($version),
            );

            return $version;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(ProductVersion $version): array
    {
        return [
            'product_id' => $version->product_id,
            'version' => $version->version,
            'released_at' => $version->released_at?->toDateString(),
            'translations' => $version->translationSnapshot(['changelog']),
        ];
    }
}
