<?php

use App\Http\Controllers\Portal\AccountSwitchController;
use App\Http\Controllers\Portal\ConversationController;
use App\Http\Controllers\Portal\DashboardController;
use App\Http\Controllers\Portal\DocumentController;
use App\Http\Controllers\Portal\MilestoneReviewController;
use App\Http\Controllers\Portal\ModuleController;
use App\Http\Controllers\Portal\NotificationController;
use App\Http\Controllers\Portal\ProfileController;
use App\Http\Controllers\Portal\ProjectController;
use App\Http\Controllers\Portal\SecurityController;
use App\Http\Controllers\Portal\SupportTicketController;
use Illuminate\Support\Facades\Route;

/*
| Client portal. Every route runs behind `portal` (EnsurePortalAccess), which resolves App\Services\CurrentCustomer.
| Record parameters are plain ulids/uuids resolved by the controllers through forCurrentCustomer(), so another
| customer's record is always a 404. Invoices and payments (Milestone 6 slice 2) still show an honest empty state.
*/

Route::get('/', DashboardController::class)->name('dashboard');
Route::post('account', AccountSwitchController::class)->middleware('throttle:30,1')->name('account.switch');

foreach (ModuleController::MODULES as $module) {
    Route::get($module, ModuleController::class)->defaults('module', $module)->name($module.'.index');
}

Route::get('projects', [ProjectController::class, 'index'])->name('projects.index');
Route::get('projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
Route::post('projects/{project}/milestones/{milestone}/review', MilestoneReviewController::class)->middleware('throttle:30,1')->name('projects.milestones.review');

Route::get('documents', [DocumentController::class, 'index'])->name('documents.index');
Route::post('documents', [DocumentController::class, 'store'])->middleware('throttle:20,1')->name('documents.store');
Route::get('documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');

Route::get('conversations', [ConversationController::class, 'index'])->name('conversations.index');
Route::post('conversations', [ConversationController::class, 'store'])->middleware('throttle:10,1')->name('conversations.store');
Route::get('conversations/{conversation}', [ConversationController::class, 'show'])->name('conversations.show');
Route::post('conversations/{conversation}/messages', [ConversationController::class, 'message'])->middleware('throttle:30,1')->name('conversations.messages.store');
Route::get('conversations/{conversation}/messages', [ConversationController::class, 'messages'])->middleware('throttle:120,1')->name('conversations.messages.index');

Route::get('support', [SupportTicketController::class, 'index'])->name('support.index');
Route::get('support/create', [SupportTicketController::class, 'create'])->name('support.create');
Route::post('support', [SupportTicketController::class, 'store'])->middleware('throttle:10,1')->name('support.store');
Route::get('support/{ticket}', [SupportTicketController::class, 'show'])->name('support.show');
Route::post('support/{ticket}/replies', [SupportTicketController::class, 'reply'])->middleware('throttle:30,1')->name('support.reply');
Route::post('support/{ticket}/resolve', [SupportTicketController::class, 'resolve'])->name('support.resolve');

Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
Route::post('notifications/{notification}/read', [NotificationController::class, 'read'])->whereUuid('notification')->name('notifications.read');

Route::get('profile', ProfileController::class)->name('profile');
Route::get('security', SecurityController::class)->middleware('password.confirm')->name('security');
