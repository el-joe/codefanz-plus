<?php

namespace App\Models;

use App\Enums\MenuLocation;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One menu per public layout location. Rendered through App\Services\MenuResolver (cached per locale).
 */
#[Fillable(['location'])]
class Menu extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'location' => MenuLocation::class,
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'location';
    }

    public function getRouteKey(): string
    {
        return $this->location->value;
    }

    /**
     * @return HasMany<MenuItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Find or create the menu for a location.
     */
    public static function for(MenuLocation $location): self
    {
        return static::query()->firstOrCreate(['location' => $location->value]);
    }
}
