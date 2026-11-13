<?php

use App\Enums\ClientVisibility;
use App\Enums\CustomerUserRole;
use App\Enums\LeadStatus;
use App\Enums\MilestoneStatus;
use App\Enums\ProjectStatus;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Lead;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\User;
use App\Notifications\MilestoneAwaitingApproval;
use App\Notifications\NewConversationMessage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Notification::fake();
    Storage::fake(config('codefanz.projects.documents.disk'));

    $this->manager = User::factory()->role(Role::ProjectManager)->create(['name' => 'Mona Adel', 'locale' => 'en']);
    $this->customer = Customer::factory()->create(['company_name' => 'ClinicPlus']);
    $this->client = User::factory()->forCustomer($this->customer, CustomerUserRole::Owner)->create(['locale' => 'ar']);
});

/**
 * @return array<string, mixed>
 */
function deliveryProjectPayload(array $overrides = []): array
{
    return [
        'name' => 'ClinicFlow booking platform',
        'description' => 'Online booking for clinics.',
        'type' => 'web',
        'status' => 'planning',
        'start_date' => '2026-11-01',
        'due_date' => '2027-01-31',
        'budget' => '24000.50',
        'currency' => 'usd',
        'show_budget_to_client' => '1',
        'show_team_to_client' => '0',
        ...$overrides,
    ];
}

test('staff without project permissions get 403', function () {
    $project = Project::factory()->forCustomer($this->customer)->create();
    $editor = User::factory()->role(Role::ContentManager)->withConfirmedTwoFactor()->create();

    $this->actingAs($editor)->get(route('admin.projects.index'))->assertForbidden();
    $this->actingAs($editor)->get(route('admin.projects.show', $project))->assertForbidden();
    $this->actingAs($editor)->post(route('admin.projects.store'), deliveryProjectPayload(['customer' => $this->customer->ulid]))->assertForbidden();
    $this->actingAs($this->client)->get(route('admin.projects.index'))->assertForbidden();
});

test('projects.view staff can read projects but not change them', function () {
    $project = Project::factory()->forCustomer($this->customer)->create(['name' => 'Readable Project']);
    $sales = User::factory()->role(Role::SalesManager)->create();

    $this->actingAs($sales)->get(route('admin.projects.index'))->assertSuccessful()->assertSee('Readable Project')->assertDontSee(route('admin.projects.create'));
    $this->actingAs($sales)->get(route('admin.projects.show', $project))->assertSuccessful()->assertDontSee(route('admin.projects.edit', $project));
    $this->actingAs($sales)->get(route('admin.projects.create'))->assertForbidden();
    $this->actingAs($sales)->put(route('admin.projects.update', $project), deliveryProjectPayload(['lock_version' => 1]))->assertForbidden();
    $this->actingAs($sales)->post(route('admin.projects.milestones.store', $project), ['title' => 'X', 'currency' => 'USD'])->assertForbidden();
    $this->actingAs($sales)->post(route('admin.projects.notes.store', $project), ['visibility' => 'internal', 'body' => 'x'])->assertForbidden();
});

test('a project manager creates a project from a customer page with team and audit', function () {
    $developer = User::factory()->role(Role::Developer)->create();

    $this->actingAs($this->manager)->get(route('admin.projects.create', ['customer' => $this->customer->ulid]))->assertSuccessful()->assertSee('ClinicPlus');

    $this->actingAs($this->manager)->post(route('admin.projects.store'), deliveryProjectPayload([
        'customer' => $this->customer->ulid,
        'project_manager_id' => $this->manager->id,
        'members' => [$developer->id],
        'member_roles' => [$developer->id => 'Backend lead'],
    ]))->assertRedirect();

    $project = Project::query()->sole();

    expect($project->reference)->toBe(sprintf('PR-%d-%05d', now()->year, $project->id))
        ->and($project->customer_id)->toBe($this->customer->id)
        ->and($project->budget_minor)->toBe(2_400_050)
        ->and($project->currency)->toBe('USD')
        ->and($project->show_team_to_client)->toBeFalse()
        ->and($project->members()->sole()->pivot->role)->toBe('Backend lead')
        ->and($project->conversations()->count())->toBe(1)
        ->and(AuditLog::query()->where('operation', 'project.created')->where('auditable_id', $project->id)->exists())->toBeTrue();

    $this->actingAs($this->manager)->get(route('admin.customers.show', $this->customer))->assertSee($project->reference);
});

test('a project can start from a won lead that became a customer', function () {
    $lead = Lead::factory()->create(['status' => LeadStatus::Won, 'company' => 'ClinicPlus', 'estimated_value_minor' => 500_000]);
    $this->customer->update(['created_from_lead_id' => $lead->id]);

    $this->actingAs($this->manager)->get(route('admin.projects.create', ['lead' => $lead->ulid]))
        ->assertSuccessful()
        ->assertSee('value="'.$this->customer->ulid.'"', false)
        ->assertSee('5000.00');

    $open = Lead::factory()->create(['status' => LeadStatus::Qualified]);
    $this->actingAs($this->manager)->get(route('admin.projects.create', ['lead' => $open->ulid]))->assertRedirect(route('admin.leads.show', $open));
});

test('project validation rejects bad input', function () {
    $this->actingAs($this->manager)->post(route('admin.projects.store'), deliveryProjectPayload([
        'customer' => 'not-a-customer',
        'name' => '',
        'status' => 'shipped',
        'due_date' => '2026-01-01',
        'budget' => '-5',
        'project_manager_id' => $this->client->id,
    ]))->assertSessionHasErrors(['customer', 'name', 'status', 'due_date', 'budget', 'project_manager_id']);

    expect(Project::query()->count())->toBe(0);
});

test('updating a project honours lock_version and audits', function () {
    $project = Project::factory()->forCustomer($this->customer)->create();

    $this->actingAs($this->manager)->put(route('admin.projects.update', $project), deliveryProjectPayload(['lock_version' => 1, 'status' => 'in_progress']))
        ->assertRedirect(route('admin.projects.show', $project));
    expect($project->fresh()->status)->toBe(ProjectStatus::InProgress)->and($project->fresh()->lock_version)->toBe(2);

    $this->actingAs($this->manager)->put(route('admin.projects.update', $project), deliveryProjectPayload(['lock_version' => 1, 'name' => 'Stale']))
        ->assertSessionHasErrors('lock_version');
    expect($project->fresh()->name)->not->toBe('Stale')
        ->and(AuditLog::query()->where('operation', 'project.updated')->count())->toBe(1);
});

test('deleting a project soft deletes it and hides it everywhere', function () {
    $project = Project::factory()->forCustomer($this->customer)->create();

    $this->actingAs($this->manager)->delete(route('admin.projects.destroy', $project))->assertRedirect(route('admin.projects.index'));

    expect($project->fresh()->trashed())->toBeTrue()
        ->and(AuditLog::query()->where('operation', 'project.deleted')->exists())->toBeTrue();
    $this->actingAs($this->client)->get(route('portal.projects.show', $project))->assertNotFound();
});

test('every project tab renders for the manager in English and Arabic', function (string $tab) {
    $project = Project::factory()->forCustomer($this->customer)->create();
    Milestone::factory()->for($project)->create();
    $project->conversations()->create(['customer_id' => $this->customer->id]);

    foreach (['en' => 'ltr', 'ar' => 'rtl'] as $locale => $direction) {
        $this->manager->forceFill(['locale' => $locale])->save();
        $this->actingAs($this->manager)->get(route('admin.projects.show', ['project' => $project, 'tab' => $tab]))
            ->assertSuccessful()
            ->assertSee('dir="'.$direction.'"', false)
            ->assertSee('data-tab="'.$tab.'"', false);
    }
})->with(['overview', 'milestones', 'documents', 'conversation', 'tickets']);

test('milestones are created, ordered and audited; paid amount is not writable', function () {
    $project = Project::factory()->forCustomer($this->customer)->create();

    $this->actingAs($this->manager)->post(route('admin.projects.milestones.store', $project), ['title' => 'UI/UX design', 'amount' => '4800', 'currency' => 'USD', 'paid_minor' => 999])->assertRedirect();
    $this->actingAs($this->manager)->post(route('admin.projects.milestones.store', $project), ['title' => 'Backend', 'amount' => '5700.25', 'currency' => 'USD'])->assertRedirect();

    [$first, $second] = $project->milestones()->get()->all();

    expect($first->title)->toBe('UI/UX design')
        ->and($first->amount_minor)->toBe(480_000)
        ->and($first->paid_minor)->toBe(0)
        ->and($second->amount_minor)->toBe(570_025)
        ->and($second->sort_order)->toBeGreaterThan($first->sort_order)
        ->and(AuditLog::query()->where('operation', 'milestone.created')->count())->toBe(2);

    $this->actingAs($this->manager)->post(route('admin.projects.milestones.move', [$project, $second]), ['direction' => 'up'])->assertRedirect();
    expect($project->milestones()->pluck('title')->all())->toBe(['Backend', 'UI/UX design']);
});

test('milestone dependencies cannot form a cycle', function () {
    $project = Project::factory()->forCustomer($this->customer)->create();
    $a = Milestone::factory()->for($project)->create(['title' => 'A']);
    $b = Milestone::factory()->for($project)->create(['title' => 'B']);
    $c = Milestone::factory()->for($project)->create(['title' => 'C']);
    $foreign = Milestone::factory()->create();

    // B depends on A, C depends on B.
    $this->actingAs($this->manager)->put(route('admin.projects.milestones.update', [$project, $b]), ['title' => 'B', 'currency' => 'USD', 'dependencies' => [$a->id], 'lock_version' => 1])->assertSessionHasNoErrors();
    $this->actingAs($this->manager)->put(route('admin.projects.milestones.update', [$project, $c]), ['title' => 'C', 'currency' => 'USD', 'dependencies' => [$b->id], 'lock_version' => 1])->assertSessionHasNoErrors();

    // A depending on C would close the loop A → C → B → A.
    $this->actingAs($this->manager)->put(route('admin.projects.milestones.update', [$project, $a]), ['title' => 'A', 'currency' => 'USD', 'dependencies' => [$c->id], 'lock_version' => 1])
        ->assertSessionHasErrors('dependencies');
    $this->actingAs($this->manager)->put(route('admin.projects.milestones.update', [$project, $a]), ['title' => 'A', 'currency' => 'USD', 'dependencies' => [$a->id], 'lock_version' => 1])
        ->assertSessionHasErrors('dependencies');
    $this->actingAs($this->manager)->put(route('admin.projects.milestones.update', [$project, $a]), ['title' => 'A', 'currency' => 'USD', 'dependencies' => [$foreign->id], 'lock_version' => 1])
        ->assertSessionHasErrors('dependencies');

    expect($a->dependencies()->count())->toBe(0);
});

test('a milestone cannot start until its dependencies are completed', function () {
    $project = Project::factory()->forCustomer($this->customer)->create();
    $design = Milestone::factory()->for($project)->status(MilestoneStatus::InProgress)->create(['title' => 'Design']);
    $build = Milestone::factory()->for($project)->create(['title' => 'Build']);
    $build->dependencies()->attach($design);

    $this->actingAs($this->manager)->post(route('admin.projects.milestones.status', [$project, $build]), ['status' => 'in_progress'])->assertSessionHasErrors('status');
    expect($build->fresh()->status)->toBe(MilestoneStatus::Pending);

    $this->actingAs($this->manager)->post(route('admin.projects.milestones.status', [$project, $design]), ['status' => 'completed'])->assertSessionHasNoErrors();
    $this->actingAs($this->manager)->post(route('admin.projects.milestones.status', [$project, $build]), ['status' => 'in_progress'])->assertSessionHasNoErrors();

    expect($build->fresh()->status)->toBe(MilestoneStatus::InProgress)
        ->and($build->fresh()->started_at)->not->toBeNull()
        ->and(AuditLog::query()->where('operation', 'milestone.status_changed')->count())->toBe(2);

    // Staff can never approve on the client's behalf.
    $this->actingAs($this->manager)->post(route('admin.projects.milestones.status', [$project, $build]), ['status' => 'approved'])->assertSessionHasErrors('status');
});

test('sending a milestone for approval notifies the client in their language', function () {
    $project = Project::factory()->forCustomer($this->customer)->create();
    $milestone = Milestone::factory()->for($project)->status(MilestoneStatus::InProgress)->create(['title' => 'Frontend']);

    $this->actingAs($this->manager)->post(route('admin.projects.milestones.status', [$project, $milestone]), ['status' => 'awaiting_approval'])->assertSessionHasNoErrors();

    expect($milestone->fresh()->status)->toBe(MilestoneStatus::AwaitingApproval);
    Notification::assertSentTo($this->client, MilestoneAwaitingApproval::class, fn ($notification, array $channels, $notifiable, ?string $locale) => $locale === 'ar' && $channels === ['database', 'mail']);
});

test('internal notes and client updates are stored separately and audited', function () {
    $project = Project::factory()->forCustomer($this->customer)->create();

    $this->actingAs($this->manager)->post(route('admin.projects.notes.store', $project), ['visibility' => 'internal', 'body' => 'Client is slow to pay.'])->assertRedirect();
    $this->actingAs($this->manager)->post(route('admin.projects.notes.store', $project), ['visibility' => 'client', 'body' => 'Staging is live.'])->assertRedirect();
    $this->actingAs($this->manager)->post(route('admin.projects.notes.store', $project), ['visibility' => 'public', 'body' => 'x'])->assertSessionHasErrors('visibility');

    expect($project->notes()->where('visibility', ClientVisibility::Internal)->count())->toBe(1)
        ->and($project->clientUpdates()->sole()->body)->toBe('Staging is live.')
        ->and(AuditLog::query()->where('operation', 'project.note_added')->count())->toBe(2);

    $this->actingAs($this->manager)->get(route('admin.projects.show', $project))->assertSee('Client is slow to pay.')->assertSee(__('Never visible to client'));
});

test('documents upload privately, version by title and download only when authorized', function () {
    $project = Project::factory()->forCustomer($this->customer)->create();
    $disk = Storage::disk(config('codefanz.projects.documents.disk'));

    $this->actingAs($this->manager)->post(route('admin.projects.documents.store', $project), [
        'file' => UploadedFile::fake()->create('Scope.pdf', 100, 'application/pdf'),
        'title' => 'Scope of work',
        'category' => 'contract',
        'visibility' => 'client',
    ])->assertRedirect();
    $this->actingAs($this->manager)->post(route('admin.projects.documents.store', $project), [
        'file' => UploadedFile::fake()->create('Scope-v2.pdf', 120, 'application/pdf'),
        'title' => 'Scope of work',
        'category' => 'contract',
        'visibility' => 'internal',
    ])->assertRedirect();

    $versions = Document::query()->orderBy('version')->get();
    expect($versions->pluck('version')->all())->toBe([1, 2])
        ->and($versions->pluck('customer_id')->unique()->all())->toBe([$this->customer->id])
        ->and($versions[0]->path)->not->toContain('Scope')
        ->and($versions[0]->path)->toStartWith('project-documents/')
        ->and(AuditLog::query()->where('operation', 'document.uploaded')->count())->toBe(2);
    $disk->assertExists($versions[0]->path);

    $this->actingAs($this->manager)->get(route('admin.documents.download', $versions[1]))
        ->assertSuccessful()
        ->assertHeader('Content-Type', 'application/octet-stream')
        ->assertDownload('Scope-v2.pdf');

    $this->actingAs(User::factory()->role(Role::ContentManager)->withConfirmedTwoFactor()->create())->get(route('admin.documents.download', $versions[1]))->assertForbidden();

    // Disallowed types are rejected.
    $this->actingAs($this->manager)->post(route('admin.projects.documents.store', $project), [
        'file' => UploadedFile::fake()->create('run.exe', 10, 'application/x-msdownload'),
        'category' => 'other',
        'visibility' => 'internal',
    ])->assertSessionHasErrors('file');
});

test('staff toggle document visibility and delete versions with audit', function () {
    $project = Project::factory()->forCustomer($this->customer)->create();
    $document = Document::factory()->for($project)->internal()->create();
    Storage::disk($document->disk)->put($document->path, 'x');

    $this->actingAs($this->manager)->patch(route('admin.projects.documents.update', [$project, $document]), ['visibility' => 'client'])->assertRedirect();
    expect($document->fresh()->visibility)->toBe(ClientVisibility::Client);

    $this->actingAs($this->manager)->delete(route('admin.projects.documents.destroy', [$project, $document]))->assertRedirect();
    expect(Document::query()->count())->toBe(0);
    Storage::disk($document->disk)->assertMissing($document->path);
    expect(AuditLog::query()->whereIn('operation', ['document.visibility_changed', 'document.deleted'])->count())->toBe(2);

    $other = Project::factory()->create();
    $foreign = Document::factory()->for($other)->create();
    $this->actingAs($this->manager)->delete(route('admin.projects.documents.destroy', [$project, $foreign]))->assertNotFound();
});

test('a staff message reaches the client users in their language and the thread polls as JSON', function () {
    $project = Project::factory()->forCustomer($this->customer)->create();
    $conversation = $project->conversations()->create(['customer_id' => $this->customer->id]);
    $colleague = User::factory()->forCustomer($this->customer)->create(['locale' => 'en']);

    $this->actingAs($this->manager)
        ->postJson(route('admin.projects.conversations.messages.store', [$project, $conversation]), ['body' => 'The M3 staging build is ready.'])
        ->assertCreated()
        ->assertJsonPath('message.body', 'The M3 staging build is ready.')
        ->assertJsonPath('message.mine', true);

    Notification::assertSentTo($this->client, NewConversationMessage::class, fn ($notification, array $channels, $notifiable, ?string $locale) => $locale === 'ar' && $notification->forClient);
    Notification::assertSentTo($colleague, NewConversationMessage::class, fn ($notification, array $channels, $notifiable, ?string $locale) => $locale === 'en');
    Notification::assertNotSentTo($this->manager, NewConversationMessage::class);

    $first = $conversation->messages()->value('id');
    $this->actingAs($this->manager)->getJson(route('admin.projects.conversations.messages.index', [$project, $conversation, 'after' => 0]))->assertJsonCount(1, 'messages');
    $this->actingAs($this->manager)->getJson(route('admin.projects.conversations.messages.index', [$project, $conversation, 'after' => $first]))->assertJsonCount(0, 'messages');

    $this->actingAs($this->manager)->postJson(route('admin.projects.conversations.messages.store', [$project, $conversation]), ['body' => str_repeat('a', 5001)])->assertUnprocessable();
    expect(AuditLog::query()->where('operation', 'conversation.message_posted')->count())->toBe(1);
});

test('the client notification mail renders in the recipient language', function () {
    $project = Project::factory()->forCustomer($this->customer)->create(['name' => 'ClinicFlow']);
    $conversation = Conversation::factory()->for($project)->create();
    $message = $conversation->messages()->create(['user_id' => $this->manager->id, 'body' => 'Hello']);

    app()->setLocale('ar');
    $mail = (new NewConversationMessage($conversation->load('project'), $message, 'Mona', forClient: true))->toMail($this->client);
    app()->setLocale('en');

    expect($mail->subject)->toBe(__('New message about :project', ['project' => 'ClinicFlow'], 'ar'))
        ->and($mail->subject)->not->toBe('New message about ClinicFlow')
        ->and($mail->actionUrl)->toBe(route('portal.conversations.show', $conversation));
});
