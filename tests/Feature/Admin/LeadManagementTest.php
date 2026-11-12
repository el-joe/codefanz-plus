<?php

use App\Enums\LeadActivityType;
use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\Role;
use App\Events\LeadCreated;
use App\Events\LeadStatusChanged;
use App\Http\Requests\ContactMessageRequest;
use App\Models\AuditLog;
use App\Models\ContactMessage;
use App\Models\Lead;
use App\Models\LeadAttachment;
use App\Models\User;
use App\Notifications\LeadAssigned;
use App\Notifications\NewLeadReceived;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->sales = User::factory()->role(Role::SalesManager)->create(['name' => 'Mona Sales', 'locale' => 'en']);
    $this->viewer = User::factory()->role(Role::ProjectManager)->create(['name' => 'Pat Viewer']);
});

test('staff without lead permissions cannot open the CRM', function () {
    $lead = Lead::factory()->create();
    $editor = User::factory()->role(Role::ContentManager)->create();

    $this->actingAs($editor)->get(route('admin.leads.index'))->assertForbidden();
    $this->actingAs($editor)->get(route('admin.leads.board'))->assertForbidden();
    $this->actingAs($editor)->get(route('admin.leads.show', $lead))->assertForbidden();
});

test('leads.view can read the table, board and detail but not change anything', function () {
    $lead = Lead::factory()->create(['name' => 'Visible Lead']);
    LeadAttachment::factory()->for($lead)->create();

    $this->actingAs($this->viewer)->get(route('admin.leads.index'))->assertSuccessful()->assertSee('Visible Lead')->assertDontSee(route('admin.leads.create'));
    $this->actingAs($this->viewer)->get(route('admin.leads.board'))->assertSuccessful()->assertSee('Visible Lead')->assertDontSee('data-lead-move', false);
    $this->actingAs($this->viewer)->get(route('admin.leads.show', $lead))->assertSuccessful()->assertDontSee(route('admin.leads.status', $lead));

    $this->actingAs($this->viewer)->get(route('admin.leads.create'))->assertForbidden();
    $this->actingAs($this->viewer)->post(route('admin.leads.activities.store', $lead), ['type' => 'note', 'body' => 'Hi'])->assertForbidden();
    $this->actingAs($this->viewer)->post(route('admin.leads.assign', $lead), ['assigned_to' => $this->sales->id, 'lock_version' => 1])->assertForbidden();
    $this->actingAs($this->viewer)->postJson(route('admin.leads.status', $lead), ['status' => 'contacted', 'lock_version' => 1])->assertForbidden();

    expect($lead->fresh()->status)->toBe(LeadStatus::New);
});

test('the table filters by status, source, owner, date and search, and sorts', function () {
    Lead::factory()->assignedTo($this->sales)->create(['name' => 'Alpha Clinic', 'created_at' => now()->subDays(10)]);
    Lead::factory()->status(LeadStatus::Qualified)->source(LeadSource::ContactForm)->create(['name' => 'Beta Retail', 'company' => 'Beta Stores']);

    $this->actingAs($this->sales);

    $this->get(route('admin.leads.index', ['status' => 'qualified']))->assertSee('Beta Retail')->assertDontSee('Alpha Clinic');
    $this->get(route('admin.leads.index', ['source' => 'quote_form']))->assertSee('Alpha Clinic')->assertDontSee('Beta Retail');
    $this->get(route('admin.leads.index', ['assignee' => 'me']))->assertSee('Alpha Clinic')->assertDontSee('Beta Retail');
    $this->get(route('admin.leads.index', ['assignee' => 'none']))->assertSee('Beta Retail')->assertDontSee('Alpha Clinic');
    $this->get(route('admin.leads.index', ['from' => now()->subDay()->toDateString()]))->assertSee('Beta Retail')->assertDontSee('Alpha Clinic');
    $this->get(route('admin.leads.index', ['q' => 'Beta Stores']))->assertSee('Beta Retail')->assertDontSee('Alpha Clinic');
    $this->get(route('admin.leads.index', ['sort' => 'name', 'direction' => 'asc']))->assertSeeInOrder(['Alpha Clinic', 'Beta Retail']);
    $this->get(route('admin.leads.index', ['sort' => 'password']))->assertSessionHasErrors('sort');
});

test('the CRM renders in arabic', function () {
    Lead::factory()->create(['name' => 'Arabic View Lead']);
    $this->sales->update(['locale' => 'ar']);

    $this->actingAs($this->sales)->withSession(['locale' => 'ar'])->get(route('admin.leads.board'))
        ->assertSuccessful()
        ->assertSee('dir="rtl"', false)
        ->assertSee('العملاء المحتملون');
});

test('a sales manager changes status through the detail form; the move is logged, audited and announced', function () {
    Event::fake([LeadStatusChanged::class]);
    $lead = Lead::factory()->create();

    $this->actingAs($this->sales)->from(route('admin.leads.show', $lead))
        ->post(route('admin.leads.status', $lead), ['status' => 'contacted', 'lock_version' => 1])
        ->assertRedirect(route('admin.leads.show', $lead))
        ->assertSessionHas('toast');

    $lead->refresh();
    expect($lead->status)->toBe(LeadStatus::Contacted)
        ->and($lead->lock_version)->toBe(2)
        ->and($lead->activities()->where('type', LeadActivityType::StatusChange)->sole()->meta)->toEqual(['from' => 'new', 'to' => 'contacted'])
        ->and(AuditLog::query()->where('operation', 'lead.status_changed')->where('auditable_id', $lead->id)->sole()->after['status'])->toBe('contacted');

    Event::assertDispatched(LeadStatusChanged::class, fn (LeadStatusChanged $event) => $event->lead->is($lead)
        && $event->from === LeadStatus::New && $event->to === LeadStatus::Contacted && $event->changedBy->is($this->sales));
});

test('moving to lost requires a reason, which is stored', function () {
    $lead = Lead::factory()->status(LeadStatus::Proposal)->create();

    $this->actingAs($this->sales)->postJson(route('admin.leads.status', $lead), ['status' => 'lost', 'lock_version' => 1])
        ->assertUnprocessable()->assertJsonValidationErrors('lost_reason');

    $this->actingAs($this->sales)->postJson(route('admin.leads.status', $lead), ['status' => 'lost', 'lost_reason' => 'Budget cut', 'lock_version' => 1])
        ->assertSuccessful();

    expect($lead->fresh()->lost_reason)->toBe('Budget cut');
});

test('invalid transitions are refused by the server', function (LeadStatus $from, string $to) {
    $lead = Lead::factory()->status($from)->create();

    $this->actingAs($this->sales)->postJson(route('admin.leads.status', $lead), ['status' => $to, 'lock_version' => 1])
        ->assertUnprocessable()->assertJsonValidationErrors('status');

    expect($lead->fresh()->status)->toBe($from);
})->with([
    'won is final' => [LeadStatus::Won, 'new'],
    'same status' => [LeadStatus::Qualified, 'qualified'],
    'lost cannot jump to won' => [LeadStatus::Lost, 'won'],
    'unknown status' => [LeadStatus::New, 'archived'],
]);

test('a lost lead can be reopened and its reason cleared', function () {
    $lead = Lead::factory()->status(LeadStatus::Lost)->create();

    $this->actingAs($this->sales)->postJson(route('admin.leads.status', $lead), ['status' => 'qualified', 'lock_version' => 1])->assertSuccessful();

    expect($lead->fresh())->status->toBe(LeadStatus::Qualified)->lost_reason->toBeNull();
});

test('the kanban move endpoint answers in JSON with the new version and allowed moves', function () {
    $lead = Lead::factory()->create();

    $this->actingAs($this->sales)->postJson(route('admin.leads.status', $lead), ['status' => 'qualified', 'lock_version' => 1])
        ->assertOk()
        ->assertJsonPath('lead.id', $lead->ulid)
        ->assertJsonPath('lead.status', 'qualified')
        ->assertJsonPath('lead.lock_version', 2)
        ->assertJsonPath('lead.allowed', fn (array $allowed) => in_array('won', $allowed, true) && ! in_array('qualified', $allowed, true));
});

test('a stale kanban card gets a 409 conflict and changes nothing', function () {
    $lead = Lead::factory()->create();
    $lead->forceFill(['lock_version' => 3])->save();

    $this->actingAs($this->sales)->postJson(route('admin.leads.status', $lead), ['status' => 'contacted', 'lock_version' => 2])
        ->assertConflict()
        ->assertJsonPath('lock_version', 3);

    expect($lead->fresh()->status)->toBe(LeadStatus::New)
        ->and(AuditLog::query()->where('operation', 'lead.status_changed')->exists())->toBeFalse();
});

test('the board shows every status column with cards and keyboard move controls', function () {
    Lead::factory()->status(LeadStatus::Negotiation)->create(['name' => 'Board Lead']);

    $this->actingAs($this->sales)->get(route('admin.leads.board'))
        ->assertSuccessful()
        ->assertSee('data-lead-column="negotiation"', false)
        ->assertSee('data-lead-column="won"', false)
        ->assertSee('Board Lead')
        ->assertSee('draggable="true"', false)
        ->assertSee('data-lead-move', false);
});

test('notes and calls are added to the timeline and audited without their content', function () {
    $lead = Lead::factory()->create();

    $this->actingAs($this->sales)->post(route('admin.leads.activities.store', $lead), ['type' => 'call', 'body' => 'Discussed scope; sending proposal Friday.'])
        ->assertRedirect(route('admin.leads.show', $lead));

    $activity = $lead->activities()->where('type', LeadActivityType::Call)->sole();
    expect($activity->user_id)->toBe($this->sales->id)
        ->and(AuditLog::query()->where('operation', 'lead.activity_logged')->sole()->after)->not->toHaveKey('body');

    $this->actingAs($this->viewer)->get(route('admin.leads.show', $lead))->assertSee('Discussed scope; sending proposal Friday.');

    $this->actingAs($this->sales)->post(route('admin.leads.activities.store', $lead), ['type' => 'status_change', 'body' => 'Forged'])
        ->assertSessionHasErrors('type');
});

test('assigning a lead notifies the assignee, logs and audits it', function () {
    Notification::fake();
    $colleague = User::factory()->role(Role::SalesManager)->create(['locale' => 'ar']);
    $lead = Lead::factory()->create();

    $this->actingAs($this->sales)->post(route('admin.leads.assign', $lead), ['assigned_to' => $colleague->id, 'lock_version' => 1])
        ->assertSessionHas('toast');

    expect($lead->fresh()->assigned_to)->toBe($colleague->id)
        ->and($lead->activities()->where('type', LeadActivityType::Assignment)->exists())->toBeTrue()
        ->and(AuditLog::query()->where('operation', 'lead.assigned')->sole()->after['assigned_to'])->toBe($colleague->id);

    Notification::assertSentTo($colleague, LeadAssigned::class, fn (LeadAssigned $notification, array $channels) => $channels === ['database', 'mail']);
    Notification::assertNotSentTo($this->sales, LeadAssigned::class);
});

test('leads can only be assigned to active staff who manage leads', function () {
    $lead = Lead::factory()->create();

    $this->actingAs($this->sales)->post(route('admin.leads.assign', $lead), ['assigned_to' => $this->viewer->id, 'lock_version' => 1])
        ->assertSessionHasErrors('assigned_to');
    $inactive = User::factory()->role(Role::SalesManager)->inactive()->create();
    $this->actingAs($this->sales)->post(route('admin.leads.assign', $lead), ['assigned_to' => $inactive->id, 'lock_version' => 1])
        ->assertSessionHasErrors('assigned_to');

    expect($lead->fresh()->assigned_to)->toBeNull();
});

test('the assignment mail is written in the assignee’s language', function () {
    $colleague = User::factory()->role(Role::SalesManager)->create(['locale' => 'ar']);
    $lead = Lead::factory()->create()->refresh();

    app()->setLocale($colleague->preferredLocale());
    $mail = (new LeadAssigned($lead, $this->sales))->toMail($colleague);

    expect($colleague->preferredLocale())->toBe('ar')
        ->and($mail->subject)->toContain($lead->reference)
        ->and($mail->subject)->toContain('تم إسناد');
});

test('a manual lead is created, assigned and announced to the other managers', function () {
    Notification::fake();
    Event::fake([LeadCreated::class]);
    $colleague = User::factory()->role(Role::SalesManager)->create();

    $this->actingAs($this->sales)->post(route('admin.leads.store'), [
        'name' => 'Walk-in Client',
        'email' => 'walkin@example.com',
        'preferred_locale' => 'en',
        'preferred_contact' => 'phone',
        'priority' => 'high',
        'assigned_to' => $colleague->id,
        'estimated_value' => '12000.50',
        'currency' => 'usd',
        'platforms' => ['web'],
    ])->assertRedirect();

    $lead = Lead::query()->sole();
    expect($lead->source)->toBe(LeadSource::Manual)
        ->and($lead->estimated_value_minor)->toBe(1_200_050)
        ->and($lead->currency)->toBe('USD')
        ->and($lead->created_by)->toBe($this->sales->id)
        ->and($lead->assigned_to)->toBe($colleague->id);

    Notification::assertSentTo($colleague, NewLeadReceived::class);
    Notification::assertNotSentTo($this->sales, NewLeadReceived::class);
    Event::assertDispatched(LeadCreated::class);
});

test('lead details are edited with optimistic locking', function () {
    $lead = Lead::factory()->create(['name' => 'Old Name']);
    $payload = ['name' => 'New Name', 'email' => $lead->email, 'preferred_locale' => 'en', 'preferred_contact' => 'email', 'priority' => 'low', 'lock_version' => 1];

    $this->actingAs($this->sales)->get(route('admin.leads.edit', $lead))->assertSuccessful();
    $this->actingAs($this->sales)->put(route('admin.leads.update', $lead), $payload)->assertRedirect(route('admin.leads.show', $lead));
    expect($lead->fresh())->name->toBe('New Name')->lock_version->toBe(2);

    $this->actingAs($this->sales)->put(route('admin.leads.update', $lead), [...$payload, 'name' => 'Stale'])->assertSessionHasErrors('lock_version');
    expect($lead->fresh()->name)->toBe('New Name')
        ->and(AuditLog::query()->where('operation', 'lead.updated')->count())->toBe(1);
});

test('attachments download only for authorized staff, always as attachment', function () {
    Storage::fake('local');
    $lead = Lead::factory()->create();
    Storage::disk('local')->put('lead-attachments/2026/11/random.pdf', '%PDF-1.4 sample');
    $attachment = LeadAttachment::factory()->for($lead)->create(['disk' => 'local', 'path' => 'lead-attachments/2026/11/random.pdf', 'original_name' => 'Brief "final".pdf']);

    $this->actingAs($this->viewer)->get(route('admin.leads.attachments.show', [$lead, $attachment]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/octet-stream')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertDownload();

    $this->actingAs(User::factory()->role(Role::ContentManager)->create())
        ->get(route('admin.leads.attachments.show', [$lead, $attachment]))->assertForbidden();

    $this->app['auth']->forgetGuards();
    $this->get(route('admin.leads.attachments.show', [$lead, $attachment]))->assertRedirect();

    $otherLead = Lead::factory()->create();
    $this->actingAs($this->viewer)->get(route('admin.leads.attachments.show', [$otherLead, $attachment]))->assertNotFound();
});

test('every new contact form message also becomes a linked lead', function () {
    Notification::fake();

    $message = ContactMessage::factory()->create();

    $this->post('/en/contact', [
        'name' => 'Contact Person',
        'email' => 'contact@example.com',
        'topic' => 'quote',
        'message' => 'We need a booking platform for clinics.',
        'preferred_locale' => 'ar',
        'consent' => '1',
        ContactMessageRequest::TIMESTAMP => Crypt::encryptString((string) now()->subMinute()->getTimestamp()),
    ])->assertRedirect();

    $created = ContactMessage::query()->whereKeyNot($message->id)->sole();
    $lead = Lead::query()->sole();

    expect($lead->source)->toBe(LeadSource::ContactForm)
        ->and($lead->contact_message_id)->toBe($created->id)
        ->and($lead->description)->toBe('We need a booking platform for clinics.')
        ->and($lead->brief)->toBe(['topic' => 'quote'])
        ->and($lead->preferred_locale)->toBe('ar');

    Notification::assertSentTo($this->sales, NewLeadReceived::class);

    $this->actingAs($this->sales)->get(route('admin.contact-messages.show', $created))->assertSee(route('admin.leads.show', $lead));
});

test('the backfill command turns old contact messages into leads once', function () {
    Notification::fake();
    Event::fake([LeadCreated::class]);
    $messages = ContactMessage::factory()->count(2)->create(['created_at' => now()->subMonth()]);

    $this->artisan('leads:backfill-contact-messages')->assertSuccessful();
    $this->artisan('leads:backfill-contact-messages')->assertSuccessful();

    expect(Lead::query()->count())->toBe(2)
        ->and(Lead::query()->pluck('contact_message_id')->sort()->values()->all())->toBe($messages->pluck('id')->sort()->values()->all())
        ->and(Lead::query()->first()->created_at->isSameDay(now()->subMonth()))->toBeTrue();

    Notification::assertNothingSent();
    Event::assertNotDispatched(LeadCreated::class);
});

test('the notification bell shows unread items and marks them read', function () {
    $lead = Lead::factory()->create()->refresh();
    $this->sales->notify(new NewLeadReceived($lead));
    $notification = $this->sales->notifications()->sole();

    $this->actingAs($this->sales)->get(route('admin.leads.index'))
        ->assertSee('data-notification-bell', false)
        ->assertSee('data-unread-count', false)
        ->assertSee('New lead '.$lead->reference.' from '.$lead->name);

    $this->actingAs($this->sales)->post(route('admin.notifications.read', $notification->id))
        ->assertRedirect(route('admin.leads.show', $lead));

    expect($notification->fresh()->read_at)->not->toBeNull();
    $this->actingAs($this->sales)->get(route('admin.leads.index'))->assertDontSee('data-unread-count', false);
});

test('users cannot read or mark other people’s notifications', function () {
    $lead = Lead::factory()->create()->refresh();
    $this->sales->notify(new NewLeadReceived($lead));
    $notification = $this->sales->notifications()->sole();

    $this->actingAs($this->viewer)->post(route('admin.notifications.read', $notification->id))->assertNotFound();

    expect($notification->fresh()->read_at)->toBeNull();
});

test('mark all as read clears the unread count', function () {
    $lead = Lead::factory()->create()->refresh();
    $this->sales->notify(new NewLeadReceived($lead));
    $this->sales->notify(new LeadAssigned($lead));

    $this->actingAs($this->sales)->post(route('admin.notifications.read-all'))->assertSessionHas('toast');

    expect($this->sales->unreadNotifications()->count())->toBe(0);
    $this->actingAs($this->sales)->get(route('admin.notifications.index'))->assertSuccessful()->assertSee('was assigned to you');
});
