<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\ChangeRequestController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\KillSwitchController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\SecurityController;
use Illuminate\Support\Facades\Route;

Route::get('security', SecurityController::class)->name('security');

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

    Route::get('audit-logs', AuditLogController::class)->middleware('can:audit.view')->name('audit-logs.index');
});
