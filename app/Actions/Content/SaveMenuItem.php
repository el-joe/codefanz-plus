<?php

namespace App\Actions\Content;

use App\Enums\MenuLinkType;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\MenuResolver;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;

class SaveMenuItem
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array{parent_id?: int|null, link_type: string, route_name?: string|null, route_query?: string|null, linkable?: string|null, url?: string|null, is_visible?: bool, open_in_new_tab?: bool, sort_order: int, translations: array<string, array<string, string|null>>}  $data
     */
    public function handle(Menu $menu, ?MenuItem $item, array $data, User $editor): MenuItem
    {
        return DB::transaction(function () use ($menu, $item, $data, $editor): MenuItem {
            $type = MenuLinkType::from($data['link_type']);
            [$linkableType, $linkableId] = $type === MenuLinkType::Linkable ? self::parseLinkable((string) $data['linkable']) : [null, null];

            $attributes = [
                'menu_id' => $menu->getKey(),
                'parent_id' => $data['parent_id'] ?? null,
                'link_type' => $type,
                'route_name' => $type === MenuLinkType::Route ? $data['route_name'] : null,
                'route_params' => $type === MenuLinkType::Route ? self::parseQuery($data['route_query'] ?? null) : null,
                'linkable_type' => $linkableType,
                'linkable_id' => $linkableId,
                'url' => $type === MenuLinkType::Url ? $data['url'] : null,
                'is_visible' => (bool) ($data['is_visible'] ?? false),
                'open_in_new_tab' => (bool) ($data['open_in_new_tab'] ?? false),
                'sort_order' => (int) $data['sort_order'],
            ];
            $before = $item ? $this->snapshot($item) : null;

            $item = $item === null ? MenuItem::create($attributes) : tap($item)->update($attributes);
            $item->syncTranslations($data['translations'], MenuItem::TRANSLATED_FIELDS, 'label');

            MenuResolver::flush();

            $this->audit->record(
                operation: $before === null ? 'menu_item.created' : 'menu_item.updated',
                auditable: $item,
                user: $editor,
                before: $before,
                after: $this->snapshot($item),
            );

            return $item;
        });
    }

    /**
     * "service:12" → ["service", 12] for an allowed morph alias.
     *
     * @return array{0: string|null, 1: int|null}
     */
    public static function parseLinkable(string $value): array
    {
        if (preg_match('/^([a-z_]+):(\d+)$/', $value, $matches) !== 1 || ! array_key_exists($matches[1], MenuItem::LINKABLE_TYPES)) {
            return [null, null];
        }

        return [$matches[1], (int) $matches[2]];
    }

    /**
     * Whether "alias:id" points at an existing, non-trashed record of an allowed type.
     */
    public static function linkableExists(string $value): bool
    {
        [$alias, $id] = self::parseLinkable($value);
        $class = $alias ? Relation::getMorphedModel($alias) : null;

        return $class !== null && $class::query()->whereKey($id)->exists();
    }

    /**
     * "category=saas&q=crm" → ["category" => "saas", "q" => "crm"] (scalar values only, locale ignored).
     *
     * @return array<string, string>|null
     */
    public static function parseQuery(?string $query): ?array
    {
        parse_str(ltrim(trim((string) $query), '?'), $params);

        $params = array_filter($params, fn (mixed $value, mixed $key): bool => is_string($key) && $key !== 'locale' && is_scalar($value) && $value !== '', ARRAY_FILTER_USE_BOTH);

        return $params === [] ? null : array_map('strval', $params);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(MenuItem $item): array
    {
        return [
            ...$item->only(['parent_id', 'route_name', 'route_params', 'linkable_type', 'linkable_id', 'url', 'is_visible', 'open_in_new_tab', 'sort_order']),
            'link_type' => $item->link_type->value,
            'translations' => $item->translationSnapshot(MenuItem::TRANSLATED_FIELDS),
        ];
    }
}
