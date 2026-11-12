<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MenuLocation;
use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class MenuController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', MenuItem::class);

        $counts = Menu::query()->withCount('items')->get()->mapWithKeys(fn (Menu $menu): array => [$menu->location->value => $menu->items_count]);

        return view('admin.menus.index', ['locations' => MenuLocation::cases(), 'counts' => $counts]);
    }

    public function show(Menu $menu): View
    {
        Gate::authorize('viewAny', MenuItem::class);

        $items = $menu->items()->with(['translations', 'linkable' => fn (MorphTo $morph) => $morph->morphWith(
            collect(MenuItem::LINKABLE_TYPES)->keys()->mapWithKeys(fn (string $alias): array => [Relation::getMorphedModel($alias) => ['translations']])->all()
        )])->get();

        return view('admin.menus.show', [
            'menu' => $menu,
            'topLevel' => $items->whereNull('parent_id')->values(),
            'children' => $items->whereNotNull('parent_id')->groupBy('parent_id'),
        ]);
    }
}
