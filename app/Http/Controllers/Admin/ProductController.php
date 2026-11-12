<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Content\SaveProduct;
use App\Actions\Content\TranslatedList;
use App\Enums\ChangeRequestStatus;
use App\Enums\ContentStatus;
use App\Enums\MediaStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveProductRequest;
use App\Models\ChangeRequest;
use App\Models\Language;
use App\Models\Media;
use App\Models\Product;
use App\Models\Technology;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Product::class);

        return view('admin.products.index', [
            'products' => Product::query()->with(['translations', 'versions'])->ordered()->paginate(30),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Product::class);

        return view('admin.products.form', [
            ...$this->formOptions(),
            'product' => new Product,
            'features' => [],
            'pendingChanges' => collect(),
        ]);
    }

    public function store(SaveProductRequest $request, SaveProduct $save): RedirectResponse
    {
        $product = $save->handle(null, $request->validated(), $request->user());

        return redirect()->route('admin.products.edit', $product)->with('toast', __('Product created as a draft.'));
    }

    public function edit(Product $product): View
    {
        Gate::authorize('update', $product);

        $product->load(['translations', 'technologies', 'cover', 'screenshots', 'versions.translations']);

        return view('admin.products.form', [
            ...$this->formOptions(),
            'product' => $product,
            'features' => TranslatedList::toRows($product->translations, 'features', SaveProduct::FEATURE_FIELDS),
            'pendingChanges' => ChangeRequest::query()
                ->whereMorphedTo('target', $product)
                ->whereIn('status', [ChangeRequestStatus::Pending, ChangeRequestStatus::UnderReview, ChangeRequestStatus::Approved])
                ->latest('id')
                ->get(),
        ]);
    }

    public function update(SaveProductRequest $request, Product $product, SaveProduct $save): RedirectResponse
    {
        $save->handle($product, $request->validated(), $request->user());

        return redirect()->route('admin.products.edit', $product)->with('toast', __('Product saved.'));
    }

    public function publish(Request $request, Product $product, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('publish', $product);

        $publish = $product->status !== ContentStatus::Published;

        if ($publish && ! $product->translations()->where('locale', Language::defaultCode())->exists()) {
            return back()->withErrors(['status' => __('Add the default-language content before publishing.')]);
        }

        $product->forceFill([
            'status' => $publish ? ContentStatus::Published : ContentStatus::Draft,
            'published_at' => $publish ? ($product->published_at ?? now()) : $product->published_at,
        ])->save();

        $audit->record(operation: $publish ? 'product.published' : 'product.unpublished', auditable: $product, user: $request->user());

        return back()->with('toast', $publish ? __('Product published.') : __('Product moved back to draft.'));
    }

    public function destroy(Request $request, Product $product, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('delete', $product);

        $product->delete();
        $audit->record(operation: 'product.deleted', auditable: $product, user: $request->user());

        return redirect()->route('admin.products.index')->with('toast', __('Product moved to trash.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'technologies' => Technology::query()->with(['translations', 'category.translations'])->ordered()->get(),
            'images' => Media::query()
                ->where('status', MediaStatus::Ready)
                ->where('mime_type', 'like', 'image/%')
                ->latest('id')
                ->limit(200)
                ->get(['id', 'uuid', 'original_name', 'alt']),
        ];
    }
}
