<?php

use App\Enums\ClientVisibility;
use App\Enums\CustomerUserRole;
use App\Enums\MilestoneStatus;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\ProjectNote;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\MilestoneReviewed;
use App\Notifications\NewConversationMessage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Notification::fake();
    Storage::fake(config('codefanz.projects.documents.disk'));

    $this->manager = User::factory()->role(Role::ProjectManager)->create(['locale' => 'ar']);

    $this->customer = Customer::factory()->create(['company_name' => 'ClinicPlus']);
    $this->client = User::factory()->forCustomer($this->customer, CustomerUserRole::Owner)->create(['locale' => 'en']);
    $this->project = Project::factory()->forCustomer($this->customer)->create(['name' => 'ClinicFlow', 'project_manager_id' => $this->manager->id]);
    $this->conversation = $this->project->conversations()->create(['customer_id' => $this->customer->id]);

    $this->otherCustomer = Customer::factory()->create(['company_name' => 'Other Co']);
    $this->otherProject = Project::factory()->forCustomer($this->otherCustomer)->create(['name' => 'Secret Project']);
    $this->otherConversation = $this->otherProject->conversations()->create(['customer_id' => $this->otherCustomer->id, 'subject' => 'Secret thread']);
    $this->otherMilestone = Milestone::factory()->for($this->otherProject)->status(MilestoneStatus::AwaitingApproval)->create();
    $this->otherDocument = Document::factory()->for($this->otherProject)->create(['title' => 'Secret contract']);
    Storage::disk($this->otherDocument->disk)->put($this->otherDocument->path, 'secret');
    $this->otherTicket = SupportTicket::factory()->create(['customer_id' => $this->otherCustomer->id, 'subject' => 'Secret ticket']);
});

test('a client never sees another customer’s projects, documents, threads or tickets (404)', function () {
    $client = $this->actingAs($this->client);

    $client->get(route('portal.projects.index'))->assertSuccessful()->assertSee('ClinicFlow')->assertDontSee('Secret Project');
    $client->get(route('portal.projects.show', $this->otherProject))->assertNotFound();
    $client->get(route('portal.projects.show', ['project' => $this->otherProject, 'tab' => 'milestones']))->assertNotFound();
    $client->post(route('portal.projects.milestones.review', [$this->otherProject, $this->otherMilestone]), ['decision' => 'approve'])->assertNotFound();
    // A foreign milestone addressed through the client's own project is also a 404.
    $client->post(route('portal.projects.milestones.review', [$this->project, $this->otherMilestone]), ['decision' => 'approve'])->assertNotFound();
    expect($this->otherMilestone->fresh()->status)->toBe(MilestoneStatus::AwaitingApproval);

    $client->get(route('portal.documents.index'))->assertSuccessful()->assertDontSee('Secret contract');
    $client->get(route('portal.documents.download', $this->otherDocument))->assertNotFound();
    $client->post(route('portal.documents.store'), ['project' => $this->otherProject->ulid, 'category' => 'other', 'file' => UploadedFile::fake()->create('x.pdf', 5, 'application/pdf')])->assertSessionHasErrors('project');
    expect(Document::query()->where('project_id', $this->otherProject->id)->count())->toBe(1);

    $client->get(route('portal.conversations.show', $this->otherConversation))->assertNotFound();
    $client->getJson(route('portal.conversations.messages.index', $this->otherConversation))->assertNotFound();
    $client->post(route('portal.conversations.messages.store', $this->otherConversation), ['body' => 'Hi'])->assertNotFound();
    $client->post(route('portal.conversations.store'), ['project' => $this->otherProject->ulid, 'subject' => 'x', 'body' => 'y'])->assertSessionHasErrors('project');
    expect($this->otherConversation->messages()->count())->toBe(0);

    $client->get(route('portal.support.index', ['status' => 'all']))->assertSuccessful()->assertDontSee('Secret ticket');
    $client->get(route('portal.support.show', $this->otherTicket))->assertNotFound();
    $client->post(route('portal.support.reply', $this->otherTicket), ['body' => 'Hi'])->assertNotFound();
    $client->post(route('portal.support.resolve', $this->otherTicket))->assertNotFound();
    $client->post(route('portal.support.store'), ['subject' => 'x', 'category' => 'bug', 'priority' => 'low', 'project' => $this->otherProject->ulid, 'body' => 'y'])->assertSessionHasErrors('project');
    expect($this->otherTicket->replies()->count())->toBe(0);
});

test('a client with access to two customers only sees the customer they are acting for', function () {
    $this->otherCustomer->users()->attach($this->client, ['role' => CustomerUserRole::Member, 'invited_at' => now()]);

    $this->actingAs($this->client)->post(route('portal.account.switch'), ['customer' => $this->customer->ulid]);
    $this->actingAs($this->client)->get(route('portal.projects.show', $this->otherProject))->assertNotFound();

    $this->actingAs($this->client)->post(route('portal.account.switch'), ['customer' => $this->otherCustomer->ulid]);
    $this->actingAs($this->client)->get(route('portal.projects.show', $this->otherProject))->assertSuccessful();
    $this->actingAs($this->client)->get(route('portal.projects.show', $this->project))->assertNotFound();
});

test('internal notes and internal documents never reach the portal HTML or JSON', function () {
    ProjectNote::factory()->for($this->project)->internal()->create(['body' => 'INTERNAL-NOTE-do-not-show']);
    ProjectNote::factory()->for($this->project)->create(['body' => 'Staging is live for review.']);
    $internal = Document::factory()->for($this->project)->internal()->create(['title' => 'INTERNAL-DOC-costing.xlsx']);
    Storage::disk($internal->disk)->put($internal->path, 'x');
    $shared = Document::factory()->for($this->project)->create(['title' => 'Shared-Agreement.pdf']);

    // An internal file attached to a message (made internal later by staff) is not listed either.
    $message = $this->conversation->messages()->create(['user_id' => $this->manager->id, 'body' => 'See attached']);
    Document::factory()->for($this->project)->internal()->create(['title' => 'INTERNAL-ATTACHMENT.pdf', 'original_name' => 'INTERNAL-ATTACHMENT.pdf', 'conversation_message_id' => $message->id]);

    foreach (['overview', 'milestones', 'documents'] as $tab) {
        $this->actingAs($this->client)->get(route('portal.projects.show', ['project' => $this->project, 'tab' => $tab]))
            ->assertSuccessful()
            ->assertDontSee('INTERNAL-NOTE-do-not-show')
            ->assertDontSee('INTERNAL-DOC-costing.xlsx');
    }

    $this->actingAs($this->client)->get(route('portal.projects.show', $this->project))->assertSee('Staging is live for review.')->assertSee('Shared-Agreement.pdf');
    $this->actingAs($this->client)->get(route('portal.documents.index'))->assertSee('Shared-Agreement.pdf')->assertDontSee('INTERNAL-DOC-costing.xlsx')->assertDontSee('INTERNAL-ATTACHMENT.pdf');
    $this->actingAs($this->client)->get(route('portal.documents.download', $internal))->assertNotFound();
    $this->actingAs($this->client)->get(route('portal.conversations.show', $this->conversation))->assertSee('See attached')->assertDontSee('INTERNAL-ATTACHMENT.pdf');
    $this->actingAs($this->client)->getJson(route('portal.conversations.messages.index', $this->conversation))
        ->assertSuccessful()
        ->assertDontSee('INTERNAL-ATTACHMENT.pdf')
        ->assertJsonPath('messages.0.attachments', []);
});

test('budget and team follow the project’s client visibility settings', function () {
    $this->project->update(['show_budget_to_client' => false, 'show_team_to_client' => false, 'budget_minor' => 2_400_000]);

    $this->actingAs($this->client)->get(route('portal.projects.show', $this->project))
        ->assertDontSee('USD 24,000')
        ->assertDontSee('data-project-team', false);

    $this->project->update(['show_budget_to_client' => true, 'show_team_to_client' => true]);

    $this->actingAs($this->client)->get(route('portal.projects.show', $this->project))
        ->assertSee('USD 24,000')
        ->assertSee($this->manager->name);
});

test('the client approves a milestone awaiting approval and the team is told', function () {
    $milestone = Milestone::factory()->for($this->project)->status(MilestoneStatus::AwaitingApproval)->create(['title' => 'Frontend']);

    $this->actingAs($this->client)->get(route('portal.projects.show', ['project' => $this->project, 'tab' => 'milestones']))
        ->assertSee(__('Approve milestone'))->assertSee(__('Request changes'));

    $this->actingAs($this->client)->post(route('portal.projects.milestones.review', [$this->project, $milestone]), ['decision' => 'approve'])
        ->assertRedirect(route('portal.projects.show', ['project' => $this->project, 'tab' => 'milestones']));

    $milestone->refresh();
    expect($milestone->status)->toBe(MilestoneStatus::Approved)
        ->and($milestone->approved_by)->toBe($this->client->id)
        ->and($milestone->approved_at)->not->toBeNull()
        ->and(AuditLog::query()->where('operation', 'milestone.approved')->where('user_id', $this->client->id)->exists())->toBeTrue();

    Notification::assertSentTo($this->manager, MilestoneReviewed::class, fn ($notification, $channels, $notifiable, ?string $locale) => $notification->approved && $locale === 'ar');

    // A second attempt is refused: it is no longer awaiting approval.
    $this->actingAs($this->client)->post(route('portal.projects.milestones.review', [$this->project, $milestone]), ['decision' => 'approve'])->assertSessionHasErrors('milestone');
});

test('the client requests changes with feedback, which sends the milestone back to in progress', function () {
    $milestone = Milestone::factory()->for($this->project)->status(MilestoneStatus::AwaitingApproval)->create();

    $this->actingAs($this->client)->post(route('portal.projects.milestones.review', [$this->project, $milestone]), ['decision' => 'changes'])->assertSessionHasErrors('feedback');
    $this->actingAs($this->client)->post(route('portal.projects.milestones.review', [$this->project, $milestone]), ['decision' => 'changes', 'feedback' => 'Add the reports page.'])->assertRedirect();

    expect($milestone->fresh()->status)->toBe(MilestoneStatus::InProgress)
        ->and($milestone->fresh()->client_feedback)->toBe('Add the reports page.')
        ->and(AuditLog::query()->where('operation', 'milestone.changes_requested')->exists())->toBeTrue();
    Notification::assertSentTo($this->manager, MilestoneReviewed::class, fn ($notification) => ! $notification->approved);

    $pending = Milestone::factory()->for($this->project)->create();
    $this->actingAs($this->client)->post(route('portal.projects.milestones.review', [$this->project, $pending]), ['decision' => 'approve'])->assertSessionHasErrors('milestone');
    expect($pending->fresh()->status)->toBe(MilestoneStatus::Pending);
});

test('a client uploads a document to their project; it is client-visible and versioned', function () {
    foreach (['Brand-guide.pdf', 'Brand-guide-v2.pdf'] as $name) {
        $this->actingAs($this->client)->post(route('portal.documents.store'), [
            'project' => $this->project->ulid,
            'title' => 'Brand guide',
            'category' => 'design',
            'file' => UploadedFile::fake()->create($name, 50, 'application/pdf'),
        ])->assertRedirect(route('portal.documents.index'));
    }

    $documents = Document::query()->where('project_id', $this->project->id)->orderBy('version')->get();
    expect($documents->pluck('version')->all())->toBe([1, 2])
        ->and($documents->every(fn (Document $document) => $document->visibility === ClientVisibility::Client && $document->uploaded_by_client))->toBeTrue();

    $this->actingAs($this->client)->get(route('portal.documents.index'))->assertSee('Brand guide')->assertSee(__('Earlier versions (:count)', ['count' => 1]));
    $this->actingAs($this->client)->get(route('portal.documents.download', $documents[1]))->assertDownload('Brand-guide-v2.pdf');

    $this->actingAs($this->client)->post(route('portal.documents.store'), [
        'project' => $this->project->ulid,
        'category' => 'other',
        'file' => UploadedFile::fake()->create('script.php', 1, 'text/x-php'),
    ])->assertSessionHasErrors('file');
});

test('a client message notifies the project team in their language and unread counts track per user', function () {
    $teammate = User::factory()->forCustomer($this->customer)->create();

    $this->actingAs($this->manager)->postJson(route('admin.projects.conversations.messages.store', [$this->project, $this->conversation]), ['body' => 'Build is ready.'])->assertCreated();
    $this->actingAs($this->manager)->postJson(route('admin.projects.conversations.messages.store', [$this->project, $this->conversation]), ['body' => 'Please test today.'])->assertCreated();

    expect(Conversation::query()->withUnreadCountFor($this->client)->find($this->conversation->id)->unread_count)->toBe(2);
    $this->actingAs($this->client)->get(route('portal.dashboard'))->assertSee('data-kpi="messages"', false)->assertSee('Please test today.')->assertViewHas('unreadMessageCount', 2);

    // Opening the thread marks it read for this user only.
    $this->actingAs($this->client)->get(route('portal.conversations.show', $this->conversation))->assertSuccessful()->assertSee('Please test today.');
    expect(Conversation::query()->withUnreadCountFor($this->client)->find($this->conversation->id)->unread_count)->toBe(0)
        ->and(Conversation::query()->withUnreadCountFor($teammate)->find($this->conversation->id)->unread_count)->toBe(2);

    $this->actingAs($this->client)->post(route('portal.conversations.messages.store', $this->conversation), [
        'body' => 'Tested, looks good!',
        'attachment' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
    ])->assertRedirect(route('portal.conversations.show', $this->conversation));

    Notification::assertSentTo($this->manager, NewConversationMessage::class, fn ($notification, $channels, $notifiable, ?string $locale) => $locale === 'ar' && ! $notification->forClient);
    expect($this->conversation->latestMessage()->first()->from_client)->toBeTrue()
        ->and(Document::query()->where('project_id', $this->project->id)->sole()->original_name)->toBe('notes.pdf')
        ->and(Conversation::query()->withUnreadCountFor($this->manager)->find($this->conversation->id)->unread_count)->toBe(1);

    $this->actingAs($this->client)->getJson(route('portal.conversations.messages.index', [$this->conversation, 'after' => 0]))
        ->assertJsonCount(3, 'messages')
        ->assertJsonPath('messages.2.mine', true)
        ->assertJsonPath('messages.2.attachments.0.name', 'notes.pdf');

    $this->actingAs($this->client)->post(route('portal.conversations.messages.store', $this->conversation), ['body' => str_repeat('x', 5001)])->assertSessionHasErrors('body');
});

test('a client starts a new thread on their project', function () {
    $this->actingAs($this->client)->post(route('portal.conversations.store'), ['project' => $this->project->ulid, 'subject' => 'Payments', 'body' => 'Question about invoices'])->assertRedirect();

    $thread = Conversation::query()->where('subject', 'Payments')->sole();
    expect($thread->customer_id)->toBe($this->customer->id)->and($thread->messages()->count())->toBe(1);
});

test('the dashboard shows real counts and upcoming milestones', function () {
    Milestone::factory()->for($this->project)->status(MilestoneStatus::InProgress)->create(['title' => 'Backend APIs']);
    SupportTicket::factory()->create(['customer_id' => $this->customer->id]);

    $this->actingAs($this->client)->get(route('portal.dashboard'))
        ->assertSuccessful()
        ->assertSee('Backend APIs')
        ->assertSee('ClinicFlow')
        ->assertDontSee('Secret Project')
        ->assertViewHas('activeProjectCount', 1)
        ->assertViewHas('openTicketCount', 1)
        ->assertViewHas('unreadMessageCount', 0);
});

test('portal delivery pages render in English and Arabic with the right direction', function (string $routeName, Closure $parameters) {
    Milestone::factory()->for($this->project)->status(MilestoneStatus::AwaitingApproval)->create();
    ProjectNote::factory()->for($this->project)->create();
    Document::factory()->for($this->project)->create();
    $ticket = SupportTicket::factory()->create(['customer_id' => $this->customer->id]);
    $ticket->replies()->create(['user_id' => $this->client->id, 'from_client' => true, 'body' => 'Help']);
    $this->conversation->messages()->create(['user_id' => $this->manager->id, 'body' => 'Hello']);

    foreach (['en' => 'ltr', 'ar' => 'rtl'] as $locale => $direction) {
        $this->client->forceFill(['locale' => $locale])->save();

        $this->actingAs($this->client)->get(route($routeName, $parameters($this, $ticket)))
            ->assertSuccessful()
            ->assertSee('<html lang="'.$locale.'" dir="'.$direction.'">', false)
            ->assertSee($locale === 'ar' ? 'مشاريعي' : 'My Projects');
    }
})->with([
    'projects' => ['portal.projects.index', fn () => []],
    'project overview' => ['portal.projects.show', fn ($test) => ['project' => $test->project]],
    'project milestones' => ['portal.projects.show', fn ($test) => ['project' => $test->project, 'tab' => 'milestones']],
    'project documents' => ['portal.projects.show', fn ($test) => ['project' => $test->project, 'tab' => 'documents']],
    'documents' => ['portal.documents.index', fn () => []],
    'conversation' => ['portal.conversations.show', fn ($test) => ['conversation' => $test->conversation]],
    'support' => ['portal.support.index', fn () => ['status' => 'all']],
    'new ticket' => ['portal.support.create', fn () => []],
    'ticket' => ['portal.support.show', fn ($test, $ticket) => ['ticket' => $ticket]],
    'dashboard' => ['portal.dashboard', fn () => []],
]);

test('opening a portal notification follows its link only inside the portal', function () {
    $mine = $this->client->notifications()->create(['id' => (string) str()->uuid(), 'type' => 'test', 'data' => [
        'kind' => 'conversation.message', 'name' => 'Mona', 'project_name' => 'ClinicFlow', 'url' => route('portal.conversations.show', $this->conversation),
    ]]);
    $evil = $this->client->notifications()->create(['id' => (string) str()->uuid(), 'type' => 'test', 'data' => ['kind' => 'x', 'url' => 'https://evil.example/portal/x']]);
    $admin = $this->client->notifications()->create(['id' => (string) str()->uuid(), 'type' => 'test', 'data' => ['kind' => 'x', 'url' => route('admin.dashboard')]]);

    $this->actingAs($this->client)->get(route('portal.notifications.index'))->assertSee(__(':name sent a message about :project', ['name' => 'Mona', 'project' => 'ClinicFlow']));
    $this->actingAs($this->client)->post(route('portal.notifications.read', $mine->id))->assertRedirect('/portal/conversations/'.$this->conversation->ulid);
    $this->actingAs($this->client)->post(route('portal.notifications.read', $evil->id))->assertRedirect('/portal/x');
    $this->actingAs($this->client)->post(route('portal.notifications.read', $admin->id))->assertRedirect(route('portal.notifications.index'));
});
