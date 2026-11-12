<?php

namespace App\Actions\Content;

use App\Models\Language;
use App\Models\Product;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Direct edits by authorized editors. Each save bumps the version, so any pending AI/MCP proposal becomes stale.
 */
class SaveProduct
{
    public const FEATURE_FIELDS = ['title', 'description'];

    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array{icon?: string|null, pricing_models: list<string>, availability: string, demo_enabled?: bool, docs_url?: string|null, sort_order: int, cover_media_id?: int|null, screenshot_media_ids?: list<int|null>|null, technology_ids?: list<int>|null, lock_version?: int|null, translations: array<string, array<string, string|null>>, features?: list<array{translations?: array<string, array<string, string|null>>}>}  $data
     */
    public function handle(?Product $product, array $data, User $editor): Product
    {
        return DB::transaction(function () use ($product, $data, $editor): Product {
            $attributes = [
                'icon' => $data['icon'] ?? null,
                'pricing_models' => array_values(array_unique($data['pricing_models'])),
                'availability' => $data['availability'],
                'demo_enabled' => (bool) ($data['demo_enabled'] ?? false),
                'docs_url' => $data['docs_url'] ?? null,
                'sort_order' => (int) $data['sort_order'],
                'updated_by' => $editor->getKey(),
            ];

            if ($product === null) {
                $product = Product::create([...$attributes, 'created_by' => $editor->getKey()]);
                $before = null;
            } else {
                $product = Product::query()->lockForUpdate()->findOrFail($product->getKey());

                if ((int) ($data['lock_version'] ?? 0) !== $product->approvableVersion()) {
                    throw ValidationException::withMessages([
                        'lock_version' => __('Someone else saved this product while you were editing. Reload to see their changes.'),
                    ]);
                }

                $before = $this->snapshot($product);
                $product->fill($attributes);
                $product->lock_version++;
                $product->save();
            }

            $features = TranslatedList::fromRows($data['features'] ?? [], self::FEATURE_FIELDS, 'title', Language::active()->keys()->all());
            $translations = collect($data['translations'])
                ->map(fn (array $fields, string $locale): array => [...$fields, 'features' => $features[$locale] ?? null])
                ->all();

            $product->syncTranslations($translations, Product::TRANSLATED_FIELDS, 'name');
            $product->technologies()->sync($data['technology_ids'] ?? []);
            $product->syncMediaCollection('cover', array_filter([(int) ($data['cover_media_id'] ?? 0)]));
            $product->syncMediaCollection('screenshots', array_values(array_unique(array_filter(array_map('intval', $data['screenshot_media_ids'] ?? [])))));

            $this->audit->record(
                operation: $before === null ? 'product.created' : 'product.updated',
                auditable: $product,
                user: $editor,
                before: $before,
                after: $this->snapshot($product),
            );

            return $product;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Product $product): array
    {
        return [
            ...$product->only(['icon', 'demo_enabled', 'docs_url', 'sort_order']),
            'pricing_models' => $product->pricing_models?->map->value->all() ?? [],
            'availability' => $product->availability->value,
            'version' => $product->lock_version,
            'translations' => $product->translationSnapshot(['name', 'slug', 'tagline', 'seo_title', 'seo_description']),
            'technology_ids' => $product->technologies()->pluck('technologies.id')->all(),
            'cover_media_id' => $product->cover()->value('media.id'),
            'screenshot_media_ids' => $product->screenshots()->pluck('media.id')->all(),
        ];
    }
}
