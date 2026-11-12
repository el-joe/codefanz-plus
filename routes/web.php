<?php

use App\Http\Controllers\AboutController;
use App\Http\Controllers\AssistantController;
use App\Http\Controllers\CareerController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DashboardRedirectController;
use App\Http\Controllers\FaqController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\IndustryController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\TechnologyController;
use App\Http\Controllers\TestimonialController;
use App\Models\Language;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function (Request $request) {
    $available = Language::active()->keys()->all();
    $locale = $request->session()->get('locale') ?? $request->getPreferredLanguage($available) ?? Language::defaultCode();

    return redirect()->route('home', ['locale' => in_array($locale, $available, true) ? $locale : Language::defaultCode()]);
});

Route::post('/locale/{code}', LocaleController::class)
    ->where('code', '[a-z]{2}')
    ->middleware('throttle:30,1')
    ->name('locale.switch');

Route::get('/dashboard', DashboardRedirectController::class)
    ->middleware(['auth', 'active'])
    ->name('dashboard');

Route::prefix('{locale}')
    ->where(['locale' => '[a-z]{2}'])
    ->middleware('locale.route')
    ->group(function (): void {
        Route::get('/', HomeController::class)->name('home');
        Route::get('/services', [ServiceController::class, 'index'])->name('services.index');
        Route::get('/services/{slug}', [ServiceController::class, 'show'])->where('slug', '[^/]+')->name('services.show');
        Route::get('/technologies', TechnologyController::class)->name('technologies.index');
        Route::get('/industries', IndustryController::class)->name('industries.index');
        Route::get('/portfolio', [PortfolioController::class, 'index'])->name('portfolio.index');
        Route::get('/portfolio/{slug}', [PortfolioController::class, 'show'])->where('slug', '[^/]+')->name('portfolio.show');
        Route::get('/products', [ProductController::class, 'index'])->name('products.index');
        Route::get('/products/{slug}', [ProductController::class, 'show'])->where('slug', '[^/]+')->name('products.show');
        Route::get('/careers', [CareerController::class, 'index'])->name('careers.index');
        Route::get('/careers/{slug}', [CareerController::class, 'show'])->where('slug', '[^/]+')->name('careers.show');
        Route::get('/faq', FaqController::class)->name('faq.index');
        Route::get('/testimonials', TestimonialController::class)->name('testimonials.index');
        Route::get('/about', AboutController::class)->name('about');
        Route::get('/contact', [ContactController::class, 'show'])->name('contact');
        Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:contact')->name('contact.store');
        Route::get('/quote', [QuoteController::class, 'show'])->name('quote');
        Route::post('/quote', [QuoteController::class, 'store'])->middleware('throttle:quote')->name('quote.store');
        Route::get('/assistant', [AssistantController::class, 'show'])->name('assistant');
        Route::post('/assistant/sessions', [AssistantController::class, 'start'])->middleware('throttle:assistant-start')->name('assistant.start');
        Route::post('/assistant/sessions/{chatSession}/messages', [AssistantController::class, 'message'])->middleware('throttle:assistant-message')->name('assistant.message');
        Route::post('/assistant/sessions/{chatSession}/answers', [AssistantController::class, 'answer'])->middleware('throttle:assistant-message')->name('assistant.answer');
        Route::post('/assistant/sessions/{chatSession}/confirm', [AssistantController::class, 'confirm'])->middleware('throttle:quote')->name('assistant.confirm');
        Route::get('/search', SearchController::class)->middleware('throttle:60,1')->name('search');

        // Catch-all CMS pages: keep last so the specific routes above win.
        Route::get('/{slug}', PageController::class)->where('slug', '[^/]+')->name('pages.show');
    });
