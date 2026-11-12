<?php

namespace App\Http\Requests\Admin;

use App\Actions\Content\SaveMenuItem;
use App\Enums\MenuLinkType;
use App\Http\Requests\Admin\Concerns\ValidatesTranslations;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Services\MenuResolver;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveMenuItemRequest extends FormRequest
{
    use ValidatesTranslations;

    public function authorize(): bool
    {
        $item = $this->route('item');

        return $item instanceof MenuItem
            ? ($this->user()?->can('update', $item) ?? false)
            : ($this->user()?->can('create', MenuItem::class) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_visible' => $this->boolean('is_visible'),
            'open_in_new_tab' => $this->boolean('open_in_new_tab'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Menu $menu */
        $menu = $this->route('menu');
        $item = $this->route('item');

        return [
            'parent_id' => ['nullable', 'integer', function (string $attribute, mixed $value, Closure $fail) use ($menu, $item): void {
                if (! $menu->location->allowsChildren()) {
                    $fail(__('Items in this menu cannot be nested.'));

                    return;
                }

                $parent = MenuItem::query()->where('menu_id', $menu->getKey())->find($value);

                if ($parent === null || $parent->parent_id !== null) {
                    $fail(__('Choose a top-level item of this menu. Menus are limited to two levels.'));
                } elseif ($item instanceof MenuItem && ($parent->is($item) || $item->children()->exists())) {
                    $fail(__('An item with sub-items cannot be nested, and an item cannot be its own parent.'));
                }
            }],
            'link_type' => ['required', Rule::enum(MenuLinkType::class)],
            'route_name' => ['nullable', 'required_if:link_type,route', Rule::in(array_keys(MenuItem::ROUTES))],
            'route_query' => ['nullable', 'string', 'max:300'],
            'linkable' => ['nullable', 'required_if:link_type,linkable', 'string', 'max:60', function (string $attribute, mixed $value, Closure $fail): void {
                if ($this->input('link_type') === MenuLinkType::Linkable->value && ! SaveMenuItem::linkableExists((string) $value)) {
                    $fail(__('Choose an existing content item.'));
                }
            }],
            'url' => ['nullable', 'required_if:link_type,url', 'string', 'max:500', 'url:https', function (string $attribute, mixed $value, Closure $fail): void {
                if (! MenuResolver::isSafeExternalUrl((string) $value)) {
                    $fail(__('Enter a full https:// link.'));
                }
            }],
            'is_visible' => ['boolean'],
            'open_in_new_tab' => ['boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            ...$this->translationRules(['label' => ['string', 'max:100']], 'label'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'url.url' => __('Enter a full https:// link.'),
        ];
    }
}
