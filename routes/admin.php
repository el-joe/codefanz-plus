<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\ChangeRequestController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\IndustryController;
use App\Http\Controllers\Admin\KillSwitchController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SecurityController;
use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\TechnologyCategoryController;
use App\Http\Controllers\Admin\TechnologyController;
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
