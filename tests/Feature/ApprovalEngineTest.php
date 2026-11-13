<?php

use App\Actions\Governance\ApplyApprovedChange;
use App\Actions\Governance\ProposeChange;
use App\Actions\Governance\ReviewChangeRequest;
use App\Enums\ChangeOrigin;
use App\Enums\ChangeRequestStatus;
use App\Enums\KillSwitchKey;
use App\Enums\Permission;
use App\Enums\Role;
use App\Exceptions\GovernanceException;
use App\Models\AuditLog;
use App\Models\ChangeRequest;
use App\Models\Page;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\KillSwitches;

function proposeSeoChange(Page $page, ?User $requester = null, ChangeOrigin $origin = ChangeOrigin::Ai): ChangeRequest
{
    return app(ProposeChange::class)->handle(
        target: $page,
        proposed: ['seo_title' => 'Web Development Services | CodeFanz'],
        origin: $origin,
        summary: 'Improve SEO title',
        requestedBy: $requester,
        actorLabel: 'content-studio',
        locale: 'en',
    );
}

beforeEach(function () {
    $this->page = Page::factory()->withTranslation('en', ['seo_title' => 'Services | CodeFanz'])->create();
    $this->reviewer = User::factory()->role(Role::SeoSpecialist)->withConfirmedTwoFactor()->create();
});

test('proposing a change records a pending request with a before snapshot and an audit entry', function () {
    $changeRequest = proposeSeoChange($this->page);

    expect($changeRequest->status)->toBe(ChangeRequestStatus::Pending)
        ->and($changeRequest->reference)->toStartWith('CR-')
        ->and($changeRequest->before['seo_title'])->toBe('Services | CodeFanz')
        ->and($changeRequest->required_permission)->toBe(Permission::SeoApprove)
        ->and($this->page->translations()->first()->seo_title)->toBe('Services | CodeFanz');

    expect(AuditLog::where('operation', 'change_request.proposed')->where('change_request_id', $changeRequest->id)->exists())->toBeTrue();
});

test('content changes require the content approval permission', function () {
    $changeRequest = app(ProposeChange::class)->handle($this->page, ['title' => 'New title'], ChangeOrigin::Ai, 'Retitle', locale: 'en');

    expect($changeRequest->required_permission)->toBe(Permission::ContentApprove);
});

test('attributes outside the approvable allow-list are rejected', function () {
    app(ProposeChange::class)->handle($this->page, ['status' => 'published'], ChangeOrigin::Ai, 'Publish', locale: 'en');
})->throws(GovernanceException::class);

test('the AI kill switch blocks new AI proposals', function () {
    app(KillSwitches::class)->set(KillSwitchKey::AiAutomation, true, $this->reviewer);

    proposeSeoChange($this->page);
})->throws(GovernanceException::class);

test('the MCP writes kill switch blocks MCP proposals', function () {
    app(KillSwitches::class)->set(KillSwitchKey::McpWrites, true, $this->reviewer);

    proposeSeoChange($this->page, origin: ChangeOrigin::Mcp);
})->throws(GovernanceException::class);

test('a reviewer cannot approve their own proposal', function () {
    $changeRequest = proposeSeoChange($this->page, requester: $this->reviewer, origin: ChangeOrigin::Mcp);

    app(ReviewChangeRequest::class)->approve($changeRequest, $this->reviewer);
})->throws(GovernanceException::class);

test('a reviewer without the required permission cannot approve', function () {
    $contentManager = User::factory()->role(Role::Client)->create();

    app(ReviewChangeRequest::class)->approve(proposeSeoChange($this->page), $contentManager);
})->throws(GovernanceException::class);

test('an approved change is applied, bumps the target version and is audited', function () {
    $changeRequest = proposeSeoChange($this->page);
    app(ReviewChangeRequest::class)->approve($changeRequest, $this->reviewer);

    app(ApplyApprovedChange::class)->handle($changeRequest, $this->reviewer);

    expect($changeRequest->fresh()->status)->toBe(ChangeRequestStatus::Applied)
        ->and($this->page->fresh()->lock_version)->toBe(2)
        ->and($this->page->translations()->first()->seo_title)->toBe('Web Development Services | CodeFanz')
        ->and(AuditLog::where('operation', 'change_request.applied')->exists())->toBeTrue();
});

test('an unapproved change cannot be applied and stays pending', function () {
    $changeRequest = proposeSeoChange($this->page);

    expect(fn () => app(ApplyApprovedChange::class)->handle($changeRequest, $this->reviewer))->toThrow(GovernanceException::class);
    expect($changeRequest->fresh()->status)->toBe(ChangeRequestStatus::Pending)
        ->and($this->page->translations()->first()->seo_title)->toBe('Services | CodeFanz');
});

test('a change whose target was edited after the proposal fails as stale', function () {
    $changeRequest = proposeSeoChange($this->page);
    app(ReviewChangeRequest::class)->approve($changeRequest, $this->reviewer);
    $this->page->forceFill(['lock_version' => 5])->save();

    expect(fn () => app(ApplyApprovedChange::class)->handle($changeRequest, $this->reviewer))->toThrow(GovernanceException::class);
    expect($changeRequest->fresh()->status)->toBe(ChangeRequestStatus::Failed)
        ->and($this->page->translations()->first()->seo_title)->toBe('Services | CodeFanz');
});

test('an expired approval cannot be applied', function () {
    $changeRequest = proposeSeoChange($this->page);
    app(ReviewChangeRequest::class)->approve($changeRequest, $this->reviewer);
    $changeRequest->forceFill(['expires_at' => now()->subMinute()])->save();

    expect(fn () => app(ApplyApprovedChange::class)->handle($changeRequest, $this->reviewer))->toThrow(GovernanceException::class);
    expect($changeRequest->fresh()->status)->toBe(ChangeRequestStatus::Failed);
});

test('a change approved by someone who lost the permission cannot be applied', function () {
    $changeRequest = proposeSeoChange($this->page);
    app(ReviewChangeRequest::class)->approve($changeRequest, $this->reviewer);
    $this->reviewer->syncRoles([Role::Client->value]);

    expect(fn () => app(ApplyApprovedChange::class)->handle($changeRequest->fresh(), $this->reviewer))->toThrow(GovernanceException::class);
    expect($changeRequest->fresh()->status)->toBe(ChangeRequestStatus::Failed);
});

test('an active kill switch blocks applying but keeps the approval for later', function () {
    $changeRequest = proposeSeoChange($this->page);
    app(ReviewChangeRequest::class)->approve($changeRequest, $this->reviewer);
    app(KillSwitches::class)->set(KillSwitchKey::AiAutomation, true, $this->reviewer);

    expect(fn () => app(ApplyApprovedChange::class)->handle($changeRequest, $this->reviewer))->toThrow(GovernanceException::class);
    expect($changeRequest->fresh()->status)->toBe(ChangeRequestStatus::Approved);
});

test('audit log entries cannot be modified or deleted', function () {
    $log = AuditLog::first() ?? app(AuditLogger::class)->record('test.event');

    expect(fn () => $log->update(['operation' => 'tampered']))->toThrow(LogicException::class)
        ->and(fn () => $log->delete())->toThrow(LogicException::class);
});
