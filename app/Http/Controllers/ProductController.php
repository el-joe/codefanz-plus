<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductTranslation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(): View
    {
        return view('products.index', [
            'products' => Product::query()->published()->translatedIn()->with(['translations', 'versions'])->ordered()->get(),
        ]);
    }

    public function show(string $slug): View
    {
        $locale = app()->getLocale();

        $translation = ProductTranslation::query()
            ->where('locale', $locale)
            ->where('slug', $slug)
            ->whereHas('product', fn (Builder $query) => $query->published())
            ->firstOrFail();

        $product = Product::query()
            ->with([
                'translations',
                'versions.translations',
                'technologies' => fn ($query) => $query->where('is_visible', true)->withProficiency()->with('translations')->ordered(),
                'cover.variants',
                'screenshots.variants',
            ])
            ->findOrFail($translation->product_id);

        $markdown = fn (?string $text): string => Str::markdown((string) $text, ['html_input' => 'strip', 'allow_unsafe_links' => false]);

        return view('products.show', [
            'product' => $product,
            'translation' => $translation,
            'description' => $markdown($translation->description),
            'changelogs' => $product->versions
                ->mapWithKeys(fn ($version): array => [$version->id => ($entry = $version->exactTranslation($locale)) ? $markdown($entry->changelog) : null])
                ->filter()
                ->all(),
        ]);
    }
}
