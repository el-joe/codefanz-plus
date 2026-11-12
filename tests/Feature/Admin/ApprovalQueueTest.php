<?php

use App\Enums\ChangeOrigin;
use App\Enums\ChangeRequestStatus;
use App\Enums\KillSwitchKey;
use App\Enums\Permission;
use App\Enums\Role;
use App\Models\AiGeneration;
use App\Models\ChangeRequest;
use App\Models\Page;
use App\Models\Service;
use App\Models\User;
use App\Notifications\ChangeRequestDecided;
use App\Services\KillSwitches;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->page = Page::factory()->withTranslation('en', ['seo_title' => 'Old title'])->create();
    $this->changeRequest = ChangeRequest::factory()->create(['target_id' => $this->page->id]);
    $this->reviewer = User::factory()->role(Role::SeoSpecialist)->create();
});

test('reviewers see pending change requests in the queue', function () {
    $this->actingAs($this->reviewer)
        ->get(route('admin.approvals.index'))
        ->assertSuccessful()
        ->assertSee($this->changeRequest->summary);
});

test('staff without an approval permission cannot open the queue', function () {
    $support = User::factory()->role(Role::SupportAgent)->create();

    $this->actingAs($support)->get(route('admin.approvals.index'))->assertForbidden();
});

test('a reviewer approves and then applies a change through the admin', function () {
    $this->actingAs($this->reviewer)
        ->post(route('admin.approvals.decide', $this->changeRequest), ['decision' => 'approve'])
        ->assertRedirect(route('admin.approvals.show', $this->changeRequest));

    $this->actingAs($this->reviewer)
        ->post(route('admin.approvals.apply', $this->changeRequest))
        ->assertRedirect(route('admin.approvals.show', $this->changeRequest));

    expect($this->changeRequest->fresh()->status)->toBe(ChangeRequestStatus::Applied)
        ->and($this->page->translations()->first()->seo_title)->toBe('New title');
});

test('requesting changes requires a note', function () {
    $this->actingAs($this->reviewer)
        ->post(route('admin.approvals.decide', $this->changeRequest), ['decision' => 'request_changes'])
        ->assertSessionHasErrors('note');

    expect($this->changeRequest->fresh()->status)->toBe(ChangeRequestStatus::Pending);
});

test('a reviewer without the required permission cannot decide', function () {
    $contentManager = User::factory()->role(Role::ContentManager)->create();

    $this->actingAs($contentManager)
        ->post(route('admin.approvals.decide', $this->changeRequest), ['decision' => 'approve'])
        ->assertForbidden();
});

test('only users with automation permission can toggle emergency controls', function () {
    $this->actingAs($this->reviewer)
        ->put(route('admin.emergency.update', KillSwitchKey::AiAutomation->value), ['active' => 1])
        ->assertForbidden();

    $admin = User::factory()->withConfirmedTwoFactor()->role(Role::Administrator)->create();

    $this->actingAs($admin)
        ->put(route('admin.emergency.update', KillSwitchKey::AiAutomation->value), ['active' => 1, 'reason' => 'Incident'])
        ->assertRedirect();

    expect(app(KillSwitches::class)->isActive(KillSwitchKey::AiAutomation))->toBeTrue();
});

test('the queue filters by status tab, origin, content type and required permission', function () {
    $service = Service::factory()->withTranslation('en')->create();
    $mcp = ChangeRequest::factory()->create(['origin' => ChangeOrigin::Mcp, 'summary' => 'MCP service meta', 'target_type' => 'service', 'target_id' => $service->id]);
    $content = ChangeRequest::factory()->create(['summary' => 'Content body rewrite', 'required_permission' => Permission::ContentApprove]);
    $applied = ChangeRequest::factory()->create(['summary' => 'Already applied', 'status' => ChangeRequestStatus::Applied]);
    $admin = User::factory()->role(Role::Administrator)->withConfirmedTwoFactor()->create();

    $this->actingAs($admin)->get(route('admin.approvals.index'))
        ->assertSee($this->changeRequest->summary)->assertSee('MCP service meta')->assertDontSee('Already applied');

    $this->actingAs($admin)->get(route('admin.approvals.index', ['origin' => 'mcp']))
        ->assertSee('MCP service meta')->assertDontSee($this->changeRequest->summary);

    $this->actingAs($admin)->get(route('admin.approvals.index', ['type' => 'service']))
        ->assertSee('MCP service meta')->assertDontSee('Content body rewrite');

    $this->actingAs($admin)->get(route('admin.approvals.index', ['permission' => 'content.approve']))
        ->assertSee('Content body rewrite')->assertDontSee('MCP service meta');

    $this->actingAs($admin)->get(route('admin.approvals.index', ['status' => 'applied']))
        ->assertSee('Already applied')->assertDontSee('MCP service meta');
});

test('the review screen shows a per-field diff, guardrail flags, re-verified checks, expiry and the generation link', function () {
    $generation = AiGeneration::factory()->create();
    $this->changeRequest->forceFill([
        'ai_generation_id' => $generation->id,
        'guardrail_flags' => [['type' => 'price', 'locale' => 'en', 'field' => 'seo_title', 'excerpt' => '$1,500']],
    ])->save();

    $this->actingAs($this->reviewer)->get(route('admin.approvals.show', $this->changeRequest))
        ->assertOk()
        ->assertSee('data-diff-field="seo_title"', false)
        ->assertSee('Old title')
        ->assertSee('New title')
        ->assertSee('data-guardrail-flags', false)
        ->assertSee('$1,500')
        ->assertSee('data-verified-checks', false)
        ->assertDontSee('data-check="fail"', false)
        ->assertSee('data-expiry', false)
        ->assertSee(route('admin.ai-studio.generations.show', $generation), false)
        ->assertSee('value="approve_apply"', false);

    // Reviewers may open the linked generation record.
    $this->actingAs($this->reviewer)->get(route('admin.ai-studio.generations.show', $generation))->assertOk();
});

test('approve and apply runs both steps; a stale target fails safely with the reason shown', function () {
    $this->actingAs($this->reviewer)
        ->post(route('admin.approvals.decide', $this->changeRequest), ['decision' => 'approve_apply', 'note' => 'Looks right'])
        ->assertRedirect(route('admin.approvals.show', $this->changeRequest));

    expect($this->changeRequest->fresh()->status)->toBe(ChangeRequestStatus::Applied)
        ->and($this->page->translations()->first()->seo_title)->toBe('New title');

    $stale = ChangeRequest::factory()->create(['target_id' => $this->page->id, 'target_version' => 1]);

    $this->actingAs($this->reviewer)->get(route('admin.approvals.show', $stale))->assertSee('data-check="fail"', false);

    $this->actingAs($this->reviewer)
        ->post(route('admin.approvals.decide', $stale), ['decision' => 'approve_apply'])
        ->assertSessionHasErrors('apply');

    expect($stale->fresh()->status)->toBe(ChangeRequestStatus::Failed);
    $this->actingAs($this->reviewer)->get(route('admin.approvals.show', $stale))->assertSee(__('Apply failed'));
});

test('applying is blocked while the AI automation kill switch is on', function () {
    app(KillSwitches::class)->set(KillSwitchKey::AiAutomation, true, $this->reviewer);

    $this->actingAs($this->reviewer)
        ->post(route('admin.approvals.decide', $this->changeRequest), ['decision' => 'approve_apply'])
        ->assertSessionHasErrors('apply');

    expect($this->changeRequest->fresh()->status)->toBe(ChangeRequestStatus::Approved)
        ->and($this->page->translations()->first()->seo_title)->toBe('Old title');
});

test('bulk approval skips AI items with guardrail flags and the reviewer’s own proposals', function () {
    $flagged = ChangeRequest::factory()->create(['guardrail_flags' => [['type' => 'banned_claim', 'locale' => 'en', 'field' => 'seo_title', 'excerpt' => 'guaranteed']]]);
    $own = ChangeRequest::factory()->create(['requested_by' => $this->reviewer->id]);
    $mcpFlagged = ChangeRequest::factory()->create(['origin' => ChangeOrigin::Mcp, 'guardrail_flags' => [['type' => 'price', 'locale' => 'en', 'field' => 'seo_title', 'excerpt' => '$5']]]);

    $this->actingAs($this->reviewer)->get(route('admin.approvals.index'))
        ->assertSee('data-bulk-disabled', false)
        ->assertSee('data-flagged', false);

    $this->actingAs($this->reviewer)
        ->post(route('admin.approvals.bulk'), ['ids' => [$this->changeRequest->id, $flagged->id, $own->id, $mcpFlagged->id]])
        ->assertRedirect();

    expect($this->changeRequest->fresh()->status)->toBe(ChangeRequestStatus::Approved)
        ->and($mcpFlagged->fresh()->status)->toBe(ChangeRequestStatus::Approved)
        ->and($flagged->fresh()->status)->toBe(ChangeRequestStatus::Pending)
        ->and($own->fresh()->status)->toBe(ChangeRequestStatus::Pending);

    $support = User::factory()->role(Role::SupportAgent)->create();
    $this->actingAs($support)->post(route('admin.approvals.bulk'), ['ids' => [$flagged->id]])->assertForbidden();
});

test('the requester is notified of the decision', function () {
    Notification::fake();
    $requester = User::factory()->role(Role::ContentManager)->create();
    $this->changeRequest->forceFill(['requested_by' => $requester->id])->save();

    $this->actingAs($this->reviewer)
        ->post(route('admin.approvals.decide', $this->changeRequest), ['decision' => 'request_changes', 'note' => 'Shorter please'])
        ->assertRedirect();

    Notification::assertSentTo($requester, ChangeRequestDecided::class, fn (ChangeRequestDecided $notification): bool => $notification->changeRequest->status === ChangeRequestStatus::ChangesRequested
        && $notification->toArray($requester)['kind'] === 'change_request.decided');
    Notification::assertNotSentTo($this->reviewer, ChangeRequestDecided::class);
});

test('the approval screens render in arabic with right-to-left direction', function () {
    $this->reviewer->forceFill(['locale' => 'ar'])->save();

    $this->actingAs($this->reviewer)->get(route('admin.approvals.index'))->assertOk()->assertSee('dir="rtl"', false)->assertSee('مفاتيح الطوارئ: كلها متوقفة');
    $this->actingAs($this->reviewer)->get(route('admin.approvals.show', $this->changeRequest))->assertOk()->assertSee('dir="rtl"', false)->assertSee('يُعاد التحقق عند التطبيق');
});
