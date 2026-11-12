<?php

namespace App\Models;

use App\Enums\MenuLinkType;
use App\Models\Concerns\HasTranslations;
use Database\Factories\MenuItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A menu link: a named public route (+ query params), a translated content item (morph) or an external https URL.
 *
 * @method MenuItemTranslation|null translation(?string $locale = null)
 * @method MenuItemTranslation|null exactTranslation(string $locale)
 */
#[Fillable(['menu_id', 'parent_id', 'link_type', 'route_name', 'route_params', 'linkable_type', 'linkable_id', 'url', 'is_visible', 'open_in_new_tab', 'sort_order'])]
class MenuItem extends Model
{
    /** @use HasFactory<MenuItemFactory> */
    use HasFactory, HasTranslations;

    public const TRANSLATED_FIELDS = ['label'];

    /**
     * Public pages a "site section" item may point to (route name => admin label key). All take only {locale}.
     */
    public const ROUTES = [
        'home' => 'Home',
        'services.index' => 'Services',
        'portfolio.index' => 'Portfolio',
        'products.index' => 'Software products',
        'technologies.index' => 'Technologies',
        'industries.index' => 'Industries',
        'careers.index' => 'Careers',
        'faq.index' => 'FAQ',
        'testimonials.index' => 'Testimonials',
    ];

    /**
     * Content types a "content item" may link to (morph alias => admin label key).
     */
    public const LINKABLE_TYPES = [
        'page' => 'Pages',
        'service' => 'Services',
        'product' => 'Software products',
        'portfolio_project' => 'Portfolio',
        'career' => 'Careers',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_visible' => true,
        'open_in_new_tab' => false,
        'sort_order' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'link_type' => MenuLinkType::class,
            'route_params' => 'array',
            'is_visible' => 'boolean',
            'open_in_new_tab' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Menu, $this>
     */
    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    /**
     * @return BelongsTo<MenuItem, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<MenuItem, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }
}
