<?php

use App\Http\Controllers\DashboardRedirectController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PageController;
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
        Route::get('/{slug}', PageController::class)->where('slug', '[^/]+')->name('pages.show');
    });
