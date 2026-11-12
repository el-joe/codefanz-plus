<?php

namespace Database\Factories;

use App\Enums\MenuLinkType;
use App\Enums\MenuLocation;
use App\Models\Menu;
use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuItem>
 */
class MenuItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'menu_id' => fn (): int => Menu::for(MenuLocation::Header)->id,
            'link_type' => MenuLinkType::Route,
            'route_name' => 'services.index',
            'route_params' => null,
            'is_visible' => true,
            'open_in_new_tab' => false,
            'sort_order' => 0,
        ];
    }

    public function in(MenuLocation $location): static
    {
        return $this->state(['menu_id' => fn (): int => Menu::for($location)->id]);
    }

    public function url(string $url): static
    {
        return $this->state(['link_type' => MenuLinkType::Url, 'route_name' => null, 'url' => $url]);
    }

    public function hidden(): static
    {
        return $this->state(['is_visible' => false]);
    }

    /**
     * @param  array<string, string>  $labels  locale => label
     */
    public function labelled(array $labels): static
    {
        return $this->afterCreating(function (MenuItem $item) use ($labels): void {
            foreach ($labels as $locale => $label) {
                $item->translations()->create(['locale' => $locale, 'label' => $label]);
            }
        });
    }
}
