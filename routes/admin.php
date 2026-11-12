<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\CareerController;
use App\Http\Controllers\Admin\ChangeRequestController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FaqCategoryController;
use App\Http\Controllers\Admin\FaqController;
use App\Http\Controllers\Admin\HomeSectionController;
use App\Http\Controllers\Admin\IndustryController;
use App\Http\Controllers\Admin\KillSwitchController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\MenuController;
use App\Http\Controllers\Admin\MenuItemController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\PortfolioCategoryController;
use App\Http\Controllers\Admin\PortfolioProjectController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProductVersionController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SecurityController;
use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\StatisticController;
use App\Http\Controllers\Admin\TechnologyCategoryController;
use App\Http\Controllers\Admin\TechnologyController;
use App\Http\Controllers\Admin\TestimonialController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::get('security', SecurityController::class)->middleware('password.confirm')->name('security');
Route::get('profile', ProfileController::class)->name('profile');

Route::middleware('two-factor')->group(function (): void {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('approvals', [ChangeRequestController::class, 'index'])->name('approvals.index');
    Route::get('approvals/{changeRequest}', [ChangeRequestController::class, 'show'])->name('approvals.show');
    Route::post('approvals/{changeRequest}/decision', [ChangeRequestController::class, 'decide'])->name('approvals.decide');
    Route::post('approvals/{changeRequest}/apply', [ChangeRequestController::class, 'apply'])->name('approvals.apply');

    Route::get('emergency', [KillSwitchController::class, 'index'])->middleware('can:automation.manage')->name('emergency.index');
    Route::put('emergency/{key}', [KillSwitchController::class, 'update'])->middleware('can:automation.manage')->name('emergency.update');

    Route::middleware('can:media.manage')->group(function (): void {
        Route::get('media', [MediaController::class, 'index'])->name('media.index');
        Route::post('media', [MediaController::class, 'store'])->middleware('throttle:30,1')->name('media.store');
        Route::get('media/{media}', [MediaController::class, 'show'])->name('media.show');
        Route::patch('media/{media}', [MediaController::class, 'update'])->name('media.update');
        Route::delete('media/{media}', [MediaController::class, 'destroy'])->name('media.destroy');
    });

    Route::resource('pages', PageController::class)->except(['show']);
    Route::post('pages/{page}/publish', [PageController::class, 'publish'])->name('pages.publish');

    Route::resource('services', ServiceController::class)->except(['show']);
    Route::post('services/{service}/publish', [ServiceController::class, 'publish'])->name('services.publish');
    Route::resource('technologies', TechnologyController::class)->except(['show']);
    Route::resource('technology-categories', TechnologyCategoryController::class)->except(['index', 'show']);
    Route::resource('industries', IndustryController::class)->except(['show']);
    Route::post('industries/{industry}/publish', [IndustryController::class, 'publish'])->name('industries.publish');
    Route::resource('portfolio', PortfolioProjectController::class)->parameters(['portfolio' => 'project'])->except(['show']);
    Route::post('portfolio/{project}/publish', [PortfolioProjectController::class, 'publish'])->name('portfolio.publish');
    Route::resource('portfolio-categories', PortfolioCategoryController::class)->except(['index', 'show']);
    Route::resource('products', ProductController::class)->except(['show']);
    Route::post('products/{product}/publish', [ProductController::class, 'publish'])->name('products.publish');
    Route::scopeBindings()->group(function (): void {
        Route::get('products/{product}/versions/create', [ProductVersionController::class, 'create'])->name('products.versions.create');
        Route::post('products/{product}/versions', [ProductVersionController::class, 'store'])->name('products.versions.store');
        Route::get('products/{product}/versions/{version}/edit', [ProductVersionController::class, 'edit'])->name('products.versions.edit');
        Route::put('products/{product}/versions/{version}', [ProductVersionController::class, 'update'])->name('products.versions.update');
        Route::delete('products/{product}/versions/{version}', [ProductVersionController::class, 'destroy'])->name('products.versions.destroy');
    });

    Route::get('home-sections', [HomeSectionController::class, 'index'])->name('home-sections.index');
    Route::get('home-sections/{section}/edit', [HomeSectionController::class, 'edit'])->name('home-sections.edit');
    Route::put('home-sections/{section}', [HomeSectionController::class, 'update'])->name('home-sections.update');
    Route::post('home-sections/{section}/visibility', [HomeSectionController::class, 'visibility'])->name('home-sections.visibility');
    Route::post('home-sections/{section}/move', [HomeSectionController::class, 'move'])->name('home-sections.move');
    Route::resource('testimonials', TestimonialController::class)->except(['show']);
    Route::post('testimonials/{testimonial}/publish', [TestimonialController::class, 'publish'])->name('testimonials.publish');
    Route::resource('faqs', FaqController::class)->except(['show']);
    Route::post('faqs/{faq}/publish', [FaqController::class, 'publish'])->name('faqs.publish');
    Route::resource('faq-categories', FaqCategoryController::class)->except(['index', 'show']);
    Route::resource('statistics', StatisticController::class)->except(['show']);
    Route::post('statistics/{statistic}/publish', [StatisticController::class, 'publish'])->name('statistics.publish');
    Route::resource('careers', CareerController::class)->except(['show']);
    Route::post('careers/{career}/publish', [CareerController::class, 'publish'])->name('careers.publish');
    Route::get('menus', [MenuController::class, 'index'])->name('menus.index');
    Route::get('menus/{menu}', [MenuController::class, 'show'])->name('menus.show');
    Route::scopeBindings()->group(function (): void {
        Route::get('menus/{menu}/items/create', [MenuItemController::class, 'create'])->name('menus.items.create');
        Route::post('menus/{menu}/items', [MenuItemController::class, 'store'])->name('menus.items.store');
        Route::get('menus/{menu}/items/{item}/edit', [MenuItemController::class, 'edit'])->name('menus.items.edit');
        Route::put('menus/{menu}/items/{item}', [MenuItemController::class, 'update'])->name('menus.items.update');
        Route::post('menus/{menu}/items/{item}/move', [MenuItemController::class, 'move'])->name('menus.items.move');
        Route::delete('menus/{menu}/items/{item}', [MenuItemController::class, 'destroy'])->name('menus.items.destroy');
    });

    Route::resource('users', UserController::class)->except(['show', 'destroy']);
    Route::post('users/{user}/invitation', [UserController::class, 'resendInvitation'])->middleware('throttle:6,1')->name('users.invitation');
    Route::delete('users/{user}/two-factor', [UserController::class, 'resetTwoFactor'])->middleware('password.confirm')->name('users.two-factor.destroy');
    Route::get('roles', RoleController::class)->name('roles.index');

    Route::middleware('can:settings.manage')->group(function (): void {
        Route::redirect('settings', 'settings/general')->name('settings.index');
        Route::get('settings/{group}', [SettingController::class, 'edit'])->whereIn('group', array_keys(config('codefanz.settings')))->name('settings.edit');
        Route::put('settings/{group}', [SettingController::class, 'update'])->whereIn('group', array_keys(config('codefanz.settings')))->name('settings.update');
    });

    Route::get('audit-logs', AuditLogController::class)->middleware('can:audit.view')->name('audit-logs.index');
});
