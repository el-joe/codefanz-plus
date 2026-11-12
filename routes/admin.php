<?php

use App\Http\Controllers\Admin\AiUsageController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\CareerController;
use App\Http\Controllers\Admin\ChangeRequestController;
use App\Http\Controllers\Admin\ContactMessageController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\CustomerPortalUserController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FaqCategoryController;
use App\Http\Controllers\Admin\FaqController;
use App\Http\Controllers\Admin\HomeSectionController;
use App\Http\Controllers\Admin\IndustryController;
use App\Http\Controllers\Admin\KillSwitchController;
use App\Http\Controllers\Admin\LeadActivityController;
use App\Http\Controllers\Admin\LeadAssignmentController;
use App\Http\Controllers\Admin\LeadAttachmentController;
use App\Http\Controllers\Admin\LeadController;
use App\Http\Controllers\Admin\LeadStatusController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\MenuController;
use App\Http\Controllers\Admin\MenuItemController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\PortfolioCategoryController;
use App\Http\Controllers\Admin\PortfolioProjectController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProductVersionController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\ProjectController;
use App\Http\Controllers\Admin\ProjectConversationController;
use App\Http\Controllers\Admin\ProjectDocumentController;
use App\Http\Controllers\Admin\ProjectMilestoneController;
use App\Http\Controllers\Admin\ProjectNoteController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SecurityController;
use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\StatisticController;
use App\Http\Controllers\Admin\SupportTicketController;
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

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'read'])->whereUuid('notification')->name('notifications.read');

    Route::middleware('can:viewAny,App\Models\Lead')->group(function (): void {
        Route::get('leads', [LeadController::class, 'index'])->name('leads.index');
        Route::get('leads/board', [LeadController::class, 'board'])->name('leads.board');
        Route::get('leads/create', [LeadController::class, 'create'])->name('leads.create');
        Route::post('leads', [LeadController::class, 'store'])->name('leads.store');
        Route::get('leads/{lead}', [LeadController::class, 'show'])->name('leads.show');
        Route::get('leads/{lead}/edit', [LeadController::class, 'edit'])->name('leads.edit');
        Route::put('leads/{lead}', [LeadController::class, 'update'])->name('leads.update');
        Route::post('leads/{lead}/status', LeadStatusController::class)->middleware('throttle:120,1')->name('leads.status');
        Route::post('leads/{lead}/assignment', LeadAssignmentController::class)->name('leads.assign');
        Route::post('leads/{lead}/activities', [LeadActivityController::class, 'store'])->name('leads.activities.store');
        Route::get('leads/{lead}/attachments/{attachment}', [LeadAttachmentController::class, 'show'])->scopeBindings()->name('leads.attachments.show');
    });

    Route::middleware('can:viewAny,App\Models\Customer')->group(function (): void {
        Route::resource('customers', CustomerController::class);
        Route::post('customers/{customer}/portal-users', [CustomerPortalUserController::class, 'store'])->middleware('throttle:20,1')->name('customers.portal-users.store');
        Route::post('customers/{customer}/portal-users/{user}/invitation', [CustomerPortalUserController::class, 'resend'])->middleware('throttle:6,1')->name('customers.portal-users.invitation');
        Route::delete('customers/{customer}/portal-users/{user}/access', [CustomerPortalUserController::class, 'revoke'])->name('customers.portal-users.revoke');
        Route::post('customers/{customer}/portal-users/{user}/access', [CustomerPortalUserController::class, 'restore'])->name('customers.portal-users.restore');
    });

    Route::middleware('can:viewAny,App\Models\Project')->group(function (): void {
        Route::resource('projects', ProjectController::class);
        Route::post('projects/{project}/notes', [ProjectNoteController::class, 'store'])->name('projects.notes.store');
        Route::post('projects/{project}/documents', [ProjectDocumentController::class, 'store'])->middleware('throttle:30,1')->name('projects.documents.store');
        Route::post('projects/{project}/conversations', [ProjectConversationController::class, 'store'])->middleware('throttle:30,1')->name('projects.conversations.store');
        Route::get('documents/{document}/download', [ProjectDocumentController::class, 'download'])->name('documents.download');

        Route::scopeBindings()->group(function (): void {
            Route::get('projects/{project}/milestones/create', [ProjectMilestoneController::class, 'create'])->name('projects.milestones.create');
            Route::post('projects/{project}/milestones', [ProjectMilestoneController::class, 'store'])->name('projects.milestones.store');
            Route::get('projects/{project}/milestones/{milestone}/edit', [ProjectMilestoneController::class, 'edit'])->name('projects.milestones.edit');
            Route::put('projects/{project}/milestones/{milestone}', [ProjectMilestoneController::class, 'update'])->name('projects.milestones.update');
            Route::post('projects/{project}/milestones/{milestone}/status', [ProjectMilestoneController::class, 'status'])->name('projects.milestones.status');
            Route::post('projects/{project}/milestones/{milestone}/move', [ProjectMilestoneController::class, 'move'])->name('projects.milestones.move');
            Route::delete('projects/{project}/milestones/{milestone}', [ProjectMilestoneController::class, 'destroy'])->name('projects.milestones.destroy');
            Route::patch('projects/{project}/documents/{document}', [ProjectDocumentController::class, 'update'])->name('projects.documents.update');
            Route::delete('projects/{project}/documents/{document}', [ProjectDocumentController::class, 'destroy'])->name('projects.documents.destroy');
            Route::post('projects/{project}/conversations/{conversation}/messages', [ProjectConversationController::class, 'message'])->middleware('throttle:60,1')->name('projects.conversations.messages.store');
            Route::get('projects/{project}/conversations/{conversation}/messages', [ProjectConversationController::class, 'messages'])->middleware('throttle:120,1')->name('projects.conversations.messages.index');
        });
    });

    Route::middleware('can:viewAny,App\Models\SupportTicket')->group(function (): void {
        Route::get('tickets', [SupportTicketController::class, 'index'])->name('tickets.index');
        Route::get('tickets/{ticket}', [SupportTicketController::class, 'show'])->name('tickets.show');
        Route::post('tickets/{ticket}/replies', [SupportTicketController::class, 'reply'])->middleware('throttle:60,1')->name('tickets.reply');
        Route::put('tickets/{ticket}', [SupportTicketController::class, 'update'])->name('tickets.update');
    });

    Route::middleware('can:leads.view')->group(function (): void {
        Route::get('contact-messages', [ContactMessageController::class, 'index'])->name('contact-messages.index');
        Route::get('contact-messages/{contactMessage}', [ContactMessageController::class, 'show'])->name('contact-messages.show');
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

    Route::get('ai-usage', AiUsageController::class)->middleware('can:settings.manage')->name('ai-usage');

    Route::get('audit-logs', AuditLogController::class)->middleware('can:audit.view')->name('audit-logs.index');
});
