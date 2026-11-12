<?php

use App\Http\Controllers\Portal\AccountSwitchController;
use App\Http\Controllers\Portal\DashboardController;
use App\Http\Controllers\Portal\ModuleController;
use App\Http\Controllers\Portal\NotificationController;
use App\Http\Controllers\Portal\ProfileController;
use App\Http\Controllers\Portal\SecurityController;
use Illuminate\Support\Facades\Route;

/*
| Client portal. Every route runs behind `portal` (EnsurePortalAccess), which resolves App\Services\CurrentCustomer.
| Modules that arrive in Milestone 6 (projects, invoices, payments, documents, conversations, support) show an
| honest empty state until their own controllers replace these routes.
*/

Route::get('/', DashboardController::class)->name('dashboard');
Route::post('account', AccountSwitchController::class)->middleware('throttle:30,1')->name('account.switch');

foreach (ModuleController::MODULES as $module) {
    Route::get($module, ModuleController::class)->defaults('module', $module)->name($module.'.index');
}

Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
Route::post('notifications/{notification}/read', [NotificationController::class, 'read'])->whereUuid('notification')->name('notifications.read');

Route::get('profile', ProfileController::class)->name('profile');
Route::get('security', SecurityController::class)->middleware('password.confirm')->name('security');
