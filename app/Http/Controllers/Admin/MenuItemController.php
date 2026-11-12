<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Content\SaveMenuItem;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveMenuItemRequest;
use App\Models\Career;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\PortfolioProject;
use App\Models\Post;
use App\Models\Product;
use App\Models\Service;
use App\Services\AuditLogger;
use App\Services\MenuResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MenuItemController extends Controller
{
    public function create(Menu $menu): View
    {
        Gate::authorize('create', MenuItem::class);

        return view('admin.menus.item-form', [...$this->formOptions($menu, null), 'menu' => $menu, 'item' => new MenuItem(['is_visible' => true])]);
    }

    public function store(SaveMenuItemRequest $request, Menu $menu, SaveMenuItem $save): RedirectResponse
    {
        $save->handle($menu, null, $request->validated(), $request->user());

        return redirect()->route('admin.menus.show', $menu)->with('toast', __('Menu item added.'));
    }

    public function edit(Menu $menu, MenuItem $item): View
    {
        Gate::authorize('update', $item);

        return view('admin.menus.item-form', [...$this->formOptions($menu, $item), 'menu' => $menu, 'item' => $item->load('translations')]);
    }

    public function update(SaveMenuItemRequest $request, Menu $menu, MenuItem $item, SaveMenuItem $save): RedirectResponse
    {
        $save->handle($menu, $item, $request->validated(), $request->user());

        return redirect()->route('admin.menus.show', $menu)->with('toast', __('Menu item saved.'));
    }

    /**
     * Swap the item with its previous or next sibling (works without JavaScript).
     */
    public function move(Request $request, Menu $menu, MenuItem $item, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('update', $item);

        $direction = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]])['direction'];

        DB::transaction(function () use ($menu, $item, $direction): void {
            $siblings = $menu->items()->where('parent_id', $item->parent_id)->lockForUpdate()->get()->values();
            $index = $siblings->search(fn (MenuItem $sibling): bool => $sibling->is($item));
            $target = $direction === 'up' ? $index - 1 : $index + 1;

            if ($index === false || ! $siblings->has($target)) {
                return;
            }

            $ordered = $siblings->all();
            [$ordered[$index], $ordered[$target]] = [$ordered[$target], $ordered[$index]];

            foreach (array_values($ordered) as $position => $sibling) {
                $sibling->forceFill(['sort_order' => $position])->save();
            }
        });

        MenuResolver::flush();
        $audit->record(operation: 'menu_item.moved', auditable: $item, user: $request->user(), after: ['direction' => $direction]);

        return back()->with('toast', __('Menu order updated.'));
    }

    public function destroy(Request $request, Menu $menu, MenuItem $item, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('delete', $item);

        $audit->record(operation: 'menu_item.deleted', auditable: $item, user: $request->user(), before: ['translations' => $item->translationSnapshot(MenuItem::TRANSLATED_FIELDS)]);
        $item->delete();
        MenuResolver::flush();

        return redirect()->route('admin.menus.show', $menu)->with('toast', __('Menu item deleted.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(Menu $menu, ?MenuItem $item): array
    {
        $title = fn (Model $model, string $field): string => $model->translations->firstWhere('locale', app()->getLocale())?->{$field}
            ?? $model->translations->first()?->{$field}
            ?? '#'.$model->getKey();

        $group = fn (string $class, string $field): array => $class::query()->with('translations')->latest('id')->limit(200)->get()
            ->mapWithKeys(fn (Model $model): array => [$model->getMorphClass().':'.$model->getKey() => $title($model, $field)])
            ->all();

        return [
            'parents' => $menu->location->allowsChildren()
                ? $menu->items()->whereNull('parent_id')->when($item?->exists, fn ($query) => $query->whereKeyNot($item->getKey()))->with('translations')->get()
                : collect(),
            'linkables' => array_filter([
                __('Pages') => $group(Page::class, 'title'),
                __('Services') => $group(Service::class, 'title'),
                __('Software products') => $group(Product::class, 'name'),
                __('Portfolio') => $group(PortfolioProject::class, 'title'),
                __('Careers') => $group(Career::class, 'title'),
                __('Blog articles') => $group(Post::class, 'title'),
            ]),
        ];
    }
}
