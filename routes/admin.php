<?php

use App\Http\Controllers\Admin\AiGenerationController;
use App\Http\Controllers\Admin\AiGovernanceController;
use App\Http\Controllers\Admin\AiUsageController;
use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\AuthorProfileController;
use App\Http\Controllers\Admin\AutomationController;
use App\Http\Controllers\Admin\BrandProfileController;
use App\Http\Controllers\Admin\CareerController;
use App\Http\Controllers\Admin\ChangeRequestController;
use App\Http\Controllers\Admin\ContactMessageController;
use App\Http\Controllers\Admin\ContentStudioController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\CustomerPortalUserController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FaqCategoryController;
use App\Http\Controllers\Admin\FaqController;
use App\Http\Controllers\Admin\HomeSectionController;
use App\Http\Controllers\Admin\IndustryController;
use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\Admin\KillSwitchController;
use App\Http\Controllers\Admin\LeadActivityController;
use App\Http\Controllers\Admin\LeadAssignmentController;
use App\Http\Controllers\Admin\LeadAttachmentController;
use App\Http\Controllers\Admin\LeadController;
use App\Http\Controllers\Admin\LeadStatusController;
use App\Http\Controllers\Admin\McpClientController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\MenuController;
use App\Http\Controllers\Admin\MenuItemController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\PaymentLinkController;
use App\Http\Controllers\Admin\PortfolioCategoryController;
use App\Http\Controllers\Admin\PortfolioProjectController;
use App\Http\Controllers\Admin\PostCategoryController;
use App\Http\Controllers\Admin\PostController;
use App\Http\Controllers\Admin\PostRevisionController;
use App\Http\Controllers\Admin\PostWorkflowController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProductVersionController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\ProjectController;
use App\Http\Controllers\Admin\ProjectConversationController;
use App\Http\Controllers\Admin\ProjectDocumentController;
use App\Http\Controllers\Admin\ProjectMilestoneController;
use App\Http\Controllers\Admin\ProjectNoteController;
use App\Http\Controllers\Admin\PromptTemplateController;
use App\Http\Controllers\Admin\RadarAnalyticsController;
use App\Http\Controllers\Admin\RadarController;
use App\Http\Controllers\Admin\RadarFilterController;
use App\Http\Controllers\Admin\RadarProposalController;
use App\Http\Controllers\Admin\RadarSettingsController;
use App\Http\Controllers\Admin\RedirectController;
use App\Http\Controllers\Admin\RefundController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SecurityController;
use App\Http\Controllers\Admin\SeoController;
use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\SocialAccountController;
use App\Http\Controllers\Admin\SocialCalendarController;
use App\Http\Controllers\Admin\SocialMetricsController;
use App\Http\Controllers\Admin\SocialPostController;
use App\Http\Controllers\Admin\SocialWorkflowController;
use App\Http\Controllers\Admin\StatisticController;
use App\Http\Controllers\Admin\SupportTicketController;
use App\Http\Controllers\Admin\TagController;
use App\Http\Controllers\Admin\TechnologyCategoryController;
use App\Http\Controllers\Admin\TechnologyController;
use App\Http\Controllers\Admin\TestimonialController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Middleware\ConfirmPasswordForSensitiveSettings;
use Illuminate\Support\Facades\Route;

Route::get('security', SecurityController::class)->middleware('password.confirm')->name('security');
Route::get('profile', ProfileController::class)->name('profile');
Route::put('profile/author', AuthorProfileController::class)->name('profile.author');

Route::middleware('two-factor')->group(function (): void {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('approvals', [ChangeRequestController::class, 'index'])->name('approvals.index');
    Route::post('approvals/bulk', [ChangeRequestController::class, 'bulk'])->middleware('throttle:20,1')->name('approvals.bulk');
    Route::get('approvals/{changeRequest}', [ChangeRequestController::class, 'show'])->name('approvals.show');
    Route::post('approvals/{changeRequest}/decision', [ChangeRequestController::class, 'decide'])->name('approvals.decide');
    Route::post('approvals/{changeRequest}/apply', [ChangeRequestController::class, 'apply'])->name('approvals.apply');

    Route::get('emergency', [KillSwitchController::class, 'index'])->middleware('can:automation.manage')->name('emergency.index');
    Route::put('emergency/{key}', [KillSwitchController::class, 'update'])->middleware('can:automation.manage')->name('emergency.update');

    Route::get('ai-governance', AiGovernanceController::class)->middleware('can:automation.manage')->name('ai-governance');

    // MCP Management (B08, M9): clients, tokens (shown once), scopes, limits, IP allow-lists and the tool-call log.
    Route::middleware('can:mcp.manage')->prefix('mcp')->name('mcp.')->group(function (): void {
        Route::get('/', [McpClientController::class, 'index'])->name('index');
        Route::get('clients/create', [McpClientController::class, 'create'])->name('clients.create');
        Route::post('clients', [McpClientController::class, 'store'])->name('clients.store');
        Route::get('clients/{client}/edit', [McpClientController::class, 'edit'])->name('clients.edit');
        Route::put('clients/{client}', [McpClientController::class, 'update'])->name('clients.update');
        Route::delete('clients/{client}', [McpClientController::class, 'destroy'])->name('clients.destroy');
        Route::post('clients/{client}/token', [McpClientController::class, 'issueToken'])->middleware('throttle:10,1')->name('clients.token.store');
        Route::delete('clients/{client}/token', [McpClientController::class, 'revokeToken'])->name('clients.token.destroy');
    });

    // Freelance Radar (B09, M10): viewing needs radar.view; every change needs radar.manage.
    Route::middleware('can:radar.view')->prefix('radar')->name('radar.')->group(function (): void {
        Route::get('/', [RadarController::class, 'index'])->name('index');
        Route::get('analytics', RadarAnalyticsController::class)->name('analytics');
        Route::get('projects/{project}', [RadarController::class, 'show'])->name('projects.show');

        Route::middleware('can:radar.manage')->group(function (): void {
            Route::post('projects/{project}/status', [RadarController::class, 'status'])->name('projects.status');
            Route::post('projects/{project}/ai-score', [RadarController::class, 'aiScore'])->middleware('throttle:20,1')->name('projects.ai-score');
            Route::post('projects/{project}/lead', [RadarController::class, 'convert'])->name('projects.convert');
            Route::post('projects/{project}/proposals', [RadarProposalController::class, 'store'])->middleware('throttle:20,1')->name('projects.proposals.store');
            Route::put('projects/{project}/proposals/{proposal}', [RadarProposalController::class, 'update'])->scopeBindings()->name('projects.proposals.update');
            Route::post('platforms/{platform}/fetch', [RadarController::class, 'fetch'])->middleware('throttle:10,1')->name('platforms.fetch');
            Route::get('settings', [RadarSettingsController::class, 'edit'])->name('settings');
            Route::put('settings', [RadarSettingsController::class, 'update'])->name('settings.update');
            Route::put('platforms/{platform}', [RadarSettingsController::class, 'platform'])->name('platforms.update');
            Route::post('telegram/test', [RadarSettingsController::class, 'telegramTest'])->middleware('throttle:6,1')->name('telegram.test');
            Route::post('rescore', [RadarSettingsController::class, 'rescore'])->middleware('throttle:6,1')->name('rescore');
            Route::get('filters/create', [RadarFilterController::class, 'create'])->name('filters.create');
            Route::post('filters', [RadarFilterController::class, 'store'])->name('filters.store');
            Route::get('filters/{filter}/edit', [RadarFilterController::class, 'edit'])->name('filters.edit');
            Route::put('filters/{filter}', [RadarFilterController::class, 'update'])->name('filters.update');
            Route::delete('filters/{filter}', [RadarFilterController::class, 'destroy'])->name('filters.destroy');
        });
    });

    // n8n integrations (B10, M10): signed outbound workflows, inbound endpoints and the run log.
    Route::middleware('can:automation.manage')->prefix('automation')->name('automation.')->group(function (): void {
        Route::get('/', [AutomationController::class, 'index'])->name('index');
        Route::get('workflows/create', [AutomationController::class, 'create'])->name('create');
        Route::post('workflows', [AutomationController::class, 'store'])->name('store');
        Route::get('workflows/{workflow}/edit', [AutomationController::class, 'edit'])->name('edit');
        Route::put('workflows/{workflow}', [AutomationController::class, 'update'])->name('update');
        Route::delete('workflows/{workflow}', [AutomationController::class, 'destroy'])->name('destroy');
        Route::post('workflows/{workflow}/toggle', [AutomationController::class, 'toggle'])->name('toggle');
        Route::post('workflows/{workflow}/secret', [AutomationController::class, 'rotate'])->middleware('throttle:10,1')->name('rotate');
        Route::post('workflows/{workflow}/test', [AutomationController::class, 'test'])->middleware('throttle:10,1')->name('test');
        Route::post('runs/{run}/retry', [AutomationController::class, 'retry'])->middleware('throttle:30,1')->name('runs.retry');
    });

    Route::middleware('can:ai.use')->prefix('ai-studio')->name('ai-studio.')->group(function (): void {
        Route::get('/', [ContentStudioController::class, 'index'])->name('index');
        Route::get('create', [ContentStudioController::class, 'create'])->name('create');
        Route::post('generations', [ContentStudioController::class, 'store'])->middleware('throttle:20,1')->name('generations.store');
        Route::get('generations', [AiGenerationController::class, 'index'])->name('generations.index');
        Route::get('generations/{generation}', [AiGenerationController::class, 'show'])->withoutMiddleware('can:ai.use')->name('generations.show');
        Route::post('generations/{generation}/draft', [AiGenerationController::class, 'draft'])->name('generations.draft');
        Route::post('generations/{generation}/propose', [AiGenerationController::class, 'propose'])->name('generations.propose');
        Route::post('generations/{generation}/save', [AiGenerationController::class, 'save'])->name('generations.save');
        Route::post('generations/{generation}/discard', [AiGenerationController::class, 'discard'])->name('generations.discard');
    });

    Route::middleware('can:ai.manage')->group(function (): void {
        Route::resource('prompts', PromptTemplateController::class)->parameters(['prompts' => 'template'])->except(['show', 'destroy']);
        Route::post('prompts/{template}/test', [PromptTemplateController::class, 'test'])->middleware('throttle:10,1')->name('prompts.test');
        Route::get('brand-voice', [BrandProfileController::class, 'edit'])->name('brand-voice.edit');
        Route::put('brand-voice', [BrandProfileController::class, 'update'])->name('brand-voice.update');
    });

    // Social media (M8.2): composer, approval workflow, calendar, manual export, accounts and metrics.
    Route::middleware('can:social.manage')->prefix('social')->name('social.')->group(function (): void {
        Route::redirect('/', 'social/posts')->name('index');
        Route::get('posts', [SocialPostController::class, 'index'])->name('posts.index');
        Route::get('posts/create', [SocialPostController::class, 'create'])->name('posts.create');
        Route::post('posts', [SocialPostController::class, 'store'])->name('posts.store');
        Route::get('posts/{post}', [SocialPostController::class, 'show'])->name('posts.show');
        Route::get('posts/{post}/edit', [SocialPostController::class, 'edit'])->name('posts.edit');
        Route::put('posts/{post}', [SocialPostController::class, 'update'])->name('posts.update');
        Route::get('posts/{post}/media/{media}', [SocialPostController::class, 'media'])->name('posts.media');
        Route::post('posts/{post}/submit', [SocialWorkflowController::class, 'submit'])->name('posts.submit');
        Route::post('posts/{post}/approve', [SocialWorkflowController::class, 'approve'])->name('posts.approve');
        Route::post('posts/{post}/request-changes', [SocialWorkflowController::class, 'requestChanges'])->name('posts.request-changes');
        Route::post('posts/{post}/schedule', [SocialWorkflowController::class, 'schedule'])->name('posts.schedule');
        Route::post('posts/{post}/reschedule', [SocialWorkflowController::class, 'reschedule'])->middleware('throttle:120,1')->name('posts.reschedule');
        Route::post('posts/{post}/unschedule', [SocialWorkflowController::class, 'unschedule'])->name('posts.unschedule');
        Route::post('posts/{post}/publish-now', [SocialWorkflowController::class, 'publishNow'])->middleware('throttle:20,1')->name('posts.publish-now');
        Route::post('posts/{post}/cancel', [SocialWorkflowController::class, 'cancel'])->name('posts.cancel');
        Route::post('variants/{variant}/retry', [SocialWorkflowController::class, 'retry'])->middleware('throttle:20,1')->name('variants.retry');
        Route::post('variants/{variant}/posted', [SocialWorkflowController::class, 'posted'])->name('variants.posted');
        Route::get('calendar', SocialCalendarController::class)->name('calendar');
        Route::get('accounts', [SocialAccountController::class, 'index'])->name('accounts.index');
        Route::post('accounts', [SocialAccountController::class, 'store'])->name('accounts.store');
        Route::get('connect/{provider}', [SocialAccountController::class, 'connect'])->middleware('throttle:10,1')->name('accounts.connect');
        Route::get('oauth/{provider}/callback', [SocialAccountController::class, 'callback'])->middleware('throttle:10,1')->name('oauth.callback');
        Route::post('accounts/{account}/verify', [SocialAccountController::class, 'verify'])->middleware('throttle:20,1')->name('accounts.verify');
        Route::post('accounts/{account}/manual', [SocialAccountController::class, 'manual'])->name('accounts.manual');
        Route::delete('accounts/{account}', [SocialAccountController::class, 'destroy'])->name('accounts.destroy');
        Route::get('metrics', [SocialMetricsController::class, 'index'])->name('metrics.index');
        Route::post('metrics/refresh', [SocialMetricsController::class, 'refresh'])->middleware('throttle:6,1')->name('metrics.refresh');
    });

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

    Route::middleware('can:viewAny,App\Models\Payment')->group(function (): void {
        Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::get('payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
        Route::post('payments/{payment}/verify', [PaymentController::class, 'verify'])->middleware('throttle:20,1')->name('payments.verify');
        Route::get('payments/{payment}/receipt', [PaymentController::class, 'receipt'])->name('payments.receipt');
        Route::get('payment-links', [PaymentLinkController::class, 'index'])->name('payment-links.index');
        Route::post('projects/{project}/payment-links', [PaymentLinkController::class, 'store'])->middleware('throttle:30,1')->name('projects.payment-links.store');
        Route::post('payment-links/{paymentLink}/cancel', [PaymentLinkController::class, 'cancel'])->name('payment-links.cancel');
        Route::post('payment-links/{paymentLink}/invoice', [InvoiceController::class, 'storeFromLink'])->name('payment-links.invoice');
        Route::post('projects/{project}/milestones/{milestone}/invoice', [InvoiceController::class, 'storeFromMilestone'])->scopeBindings()->name('projects.milestones.invoice');
        Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
        Route::put('invoices/{invoice}', [InvoiceController::class, 'update'])->name('invoices.update');
        Route::post('invoices/{invoice}/issue', [InvoiceController::class, 'issue'])->name('invoices.issue');
        Route::post('invoices/{invoice}/void', [InvoiceController::class, 'void'])->name('invoices.void');
        Route::get('invoices/{invoice}/print', [InvoiceController::class, 'print'])->name('invoices.print');
        Route::get('payments-export', [PaymentController::class, 'export'])->middleware('throttle:10,1')->name('payments.export');
        Route::post('payments/{payment}/refunds', [RefundController::class, 'store'])->middleware('throttle:20,1')->name('payments.refunds.store');
        Route::get('refunds', [RefundController::class, 'index'])->name('refunds.index');
        Route::post('refunds/{refund}/approve', [RefundController::class, 'approve'])->middleware('throttle:20,1')->name('refunds.approve');
        Route::post('refunds/{refund}/reject', [RefundController::class, 'reject'])->name('refunds.reject');
        Route::post('refunds/{refund}/complete', [RefundController::class, 'complete'])->name('refunds.complete');
        Route::get('credit-notes/{creditNote}/print', [RefundController::class, 'creditNote'])->name('credit-notes.print');
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

    Route::get('posts', [PostController::class, 'index'])->name('posts.index');
    Route::get('posts/create', [PostController::class, 'create'])->name('posts.create');
    Route::post('posts', [PostController::class, 'store'])->name('posts.store');
    Route::post('posts/checklist', [PostController::class, 'checklist'])->middleware('throttle:120,1')->name('posts.checklist');
    Route::get('posts/{post}/edit', [PostController::class, 'edit'])->name('posts.edit');
    Route::put('posts/{post}', [PostController::class, 'update'])->name('posts.update');
    Route::delete('posts/{post}', [PostController::class, 'destroy'])->name('posts.destroy');
    Route::post('posts/{post}/submit', [PostWorkflowController::class, 'submit'])->name('posts.submit');
    Route::post('posts/{post}/approve', [PostWorkflowController::class, 'approve'])->name('posts.approve');
    Route::post('posts/{post}/request-changes', [PostWorkflowController::class, 'requestChanges'])->name('posts.request-changes');
    Route::post('posts/{post}/publish', [PostWorkflowController::class, 'publish'])->name('posts.publish');
    Route::post('posts/{post}/schedule', [PostWorkflowController::class, 'schedule'])->name('posts.schedule');
    Route::post('posts/{post}/unschedule', [PostWorkflowController::class, 'unschedule'])->name('posts.unschedule');
    Route::post('posts/{post}/archive', [PostWorkflowController::class, 'archive'])->name('posts.archive');
    Route::post('posts/{post}/reopen', [PostWorkflowController::class, 'reopen'])->name('posts.reopen');
    Route::get('posts/{post}/revisions', [PostRevisionController::class, 'index'])->name('posts.revisions.index');
    Route::post('posts/{post}/revisions/{revision}/restore', [PostRevisionController::class, 'restore'])->scopeBindings()->name('posts.revisions.restore');
    Route::resource('post-categories', PostCategoryController::class)->except(['show']);
    Route::resource('tags', TagController::class)->except(['index', 'show']);

    Route::middleware('can:seo.manage')->group(function (): void {
        Route::get('seo', [SeoController::class, 'audit'])->name('seo.audit');
        Route::post('seo/audits', [SeoController::class, 'runAudit'])->middleware('throttle:6,1')->name('seo.audits.store');
        Route::get('seo/meta', [SeoController::class, 'meta'])->name('seo.meta');
        Route::put('seo/meta', [SeoController::class, 'updateMeta'])->name('seo.meta.update');
        Route::get('seo/sitemap', [SeoController::class, 'sitemap'])->name('seo.sitemap');
        Route::post('seo/issues/{issue}/ai-fix', [SeoController::class, 'aiFix'])->middleware(['can:ai.use', 'throttle:20,1'])->name('seo.issues.ai-fix');
        Route::post('redirects/import', [RedirectController::class, 'import'])->middleware('throttle:10,1')->name('redirects.import');
        Route::resource('redirects', RedirectController::class)->except(['show']);
        Route::get('analytics', [AnalyticsController::class, 'index'])->name('analytics.index');
        Route::post('analytics/sync', [AnalyticsController::class, 'sync'])->middleware('throttle:6,1')->name('analytics.sync');
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
        // Payment credentials need a recently confirmed password (ConfirmPasswordForSensitiveSettings).
        Route::get('settings/{group}', [SettingController::class, 'edit'])->whereIn('group', array_keys(config('codefanz.settings')))->middleware(ConfirmPasswordForSensitiveSettings::class)->name('settings.edit');
        Route::put('settings/{group}', [SettingController::class, 'update'])->whereIn('group', array_keys(config('codefanz.settings')))->middleware(ConfirmPasswordForSensitiveSettings::class)->name('settings.update');
        Route::post('settings/payments/gateways/{gateway}/test', [SettingController::class, 'testGateway'])->middleware(['password.confirm', 'throttle:10,1'])->name('settings.payments.test');
    });

    Route::get('ai-usage', AiUsageController::class)->middleware('can:settings.manage')->name('ai-usage');

    Route::get('audit-logs', AuditLogController::class)->middleware('can:audit.view')->name('audit-logs.index');
});
