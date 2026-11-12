<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Content\SaveProductVersion;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveProductVersionRequest;
use App\Models\Product;
use App\Models\ProductVersion;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Versions are managed from the product edit screen; editing a version requires permission to update its product.
 */
class ProductVersionController extends Controller
{
    public function create(Product $product): View
    {
        Gate::authorize('update', $product);

        return view('admin.products.version-form', ['product' => $product->load('translations'), 'version' => new ProductVersion]);
    }

    public function store(SaveProductVersionRequest $request, Product $product, SaveProductVersion $save): RedirectResponse
    {
        $save->handle($product, null, $request->validated(), $request->user());

        return redirect()->route('admin.products.edit', $product)->with('toast', __('Version added.'));
    }

    public function edit(Product $product, ProductVersion $version): View
    {
        Gate::authorize('update', $product);

        return view('admin.products.version-form', ['product' => $product->load('translations'), 'version' => $version->load('translations')]);
    }

    public function update(SaveProductVersionRequest $request, Product $product, ProductVersion $version, SaveProductVersion $save): RedirectResponse
    {
        $save->handle($product, $version, $request->validated(), $request->user());

        return redirect()->route('admin.products.edit', $product)->with('toast', __('Version saved.'));
    }

    public function destroy(Request $request, Product $product, ProductVersion $version, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('update', $product);

        $before = ['product_id' => $product->id, 'version' => $version->version];
        $version->delete();
        $audit->record(operation: 'product_version.deleted', auditable: $version, user: $request->user(), before: $before);

        return redirect()->route('admin.products.edit', $product)->with('toast', __('Version deleted.'));
    }
}
