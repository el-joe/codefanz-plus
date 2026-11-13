<?php

namespace App\Http\Resources\Mcp;

use App\Contracts\Approvable;
use App\Services\Mcp\McpContentTypes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A website item for MCP. Lists carry title, slug and summary per language; get_content carries every field a
 * change request may modify, plus the version a proposal is checked against.
 *
 * @mixin Model&Approvable
 */
class ContentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Model&Approvable $item */
        $item = $this->resource;
        $types = app(McpContentTypes::class);
        $type = $item->getMorphClass();
        $detailed = $request->routeIs('api.mcp.get_content');
        $locale = $request->query('locale');
        $fields = $detailed
            ? $item->approvableAttributes()
            : array_values(array_filter([$types->titleField($type), 'slug', $types->summaryField($type)]));

        $translations = [];

        foreach ($item->translations as $translation) {
            if (is_string($locale) && $locale !== '' && $translation->locale !== $locale) {
                continue;
            }

            $translations[$translation->locale] = [
                ...$translation->only($fields),
                'url' => $types->publicUrl($type, $item, $translation->locale),
            ];
        }

        return [
            'type' => $type,
            'id' => $item->getKey(),
            'status' => $item->status?->value,
            'is_public' => $types->isPublic($item),
            'version' => $item->approvableVersion(),
            'published_at' => $item->published_at?->toIso8601String(),
            'updated_at' => $item->updated_at?->toIso8601String(),
            'translations' => (object) $translations,
            ...($detailed ? ['editable_fields' => $item->approvableAttributes()] : []),
        ];
    }
}
