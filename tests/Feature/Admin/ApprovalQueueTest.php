<?php

use App\Enums\ChangeRequestStatus;
use App\Enums\KillSwitchKey;
use App\Enums\Role;
use App\Models\ChangeRequest;
use App\Models\Page;
use App\Models\User;
use App\Services\KillSwitches;

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
