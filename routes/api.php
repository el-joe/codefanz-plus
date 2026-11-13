<?php

use App\Http\Controllers\Api\V1\Mcp\AnalyticsController;
use App\Http\Controllers\Api\V1\Mcp\ApprovalController;
use App\Http\Controllers\Api\V1\Mcp\ClientController;
use App\Http\Controllers\Api\V1\Mcp\ContentController;
use App\Http\Controllers\Api\V1\Mcp\LeadController;
use App\Http\Controllers\Api\V1\Mcp\PostDraftController;
use App\Http\Controllers\Api\V1\Mcp\SeoController;
use App\Http\Controllers\Api\V1\Mcp\SocialController;
use App\Services\Mcp\McpContentTypes;
use Illuminate\Support\Facades\Route;

/*
| MCP API (M9), consumed by the standalone TypeScript server in mcp-server/. Each route name after "api.mcp." is
| the MCP tool name recorded in mcp_tool_calls. The "mcp:<scope>" middleware enforces the client's status, IP
| allow-list, rate limit, token scope, owner permission and the kill switches (docs/MCP_DOCUMENTATION.md).
*/

Route::prefix('v1/mcp')->name('api.mcp.')->middleware('auth:sanctum')->group(function (): void {
    Route::get('me', ClientController::class)->middleware('mcp')->name('whoami');

    Route::middleware('mcp:content:read')->group(function (): void {
        Route::get('content/{type}', [ContentController::class, 'index'])->whereIn('type', McpContentTypes::TYPES)->name('list_content');
        Route::get('content/{type}/{key}', [ContentController::class, 'show'])->whereIn('type', McpContentTypes::TYPES)->name('get_content');
    });

    Route::middleware('mcp:content:draft')->group(function (): void {
        Route::post('content/{type}/{id}/proposals', [ContentController::class, 'propose'])->whereIn('type', McpContentTypes::TYPES)->whereNumber('id')->name('propose_content_change');
        Route::post('posts/drafts', PostDraftController::class)->name('create_blog_draft');
    });

    Route::middleware('mcp:leads:read')->group(function (): void {
        Route::get('leads', [LeadController::class, 'index'])->name('list_leads');
        Route::get('leads/{reference}', [LeadController::class, 'show'])->name('get_lead');
    });
    Route::post('leads', [LeadController::class, 'store'])->middleware('mcp:leads:write')->name('create_lead');

    Route::middleware('mcp:seo:read')->group(function (): void {
        Route::get('seo/audit', [SeoController::class, 'audit'])->name('get_seo_audit');
        Route::get('seo/issues', [SeoController::class, 'issues'])->name('list_seo_issues');
    });
    Route::post('seo/proposals', [SeoController::class, 'propose'])->middleware('mcp:seo:propose')->name('propose_seo_meta');

    Route::get('analytics/summary', AnalyticsController::class)->middleware('mcp:analytics:read')->name('get_analytics_summary');

    Route::middleware('mcp:approvals:read')->group(function (): void {
        Route::get('approvals', [ApprovalController::class, 'index'])->name('list_approvals');
        Route::get('approvals/{reference}', [ApprovalController::class, 'show'])->name('get_approval');
    });
    Route::post('approvals/{reference}/apply', [ApprovalController::class, 'apply'])->middleware('mcp:approvals:apply')->name('apply_approved_change');

    Route::middleware('mcp:social:draft')->group(function (): void {
        Route::get('social/accounts', [SocialController::class, 'accounts'])->name('list_social_accounts');
        Route::post('social/drafts', [SocialController::class, 'store'])->name('create_social_draft');
    });
});
