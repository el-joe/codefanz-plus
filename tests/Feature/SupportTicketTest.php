<?php

use App\Enums\CustomerUserRole;
use App\Enums\Role;
use App\Enums\TicketStatus;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Project;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\SupportTicketUpdated;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();

    $this->agent = User::factory()->role(Role::SupportAgent)->create(['name' => 'Khaled Hany', 'locale' => 'ar']);
    $this->customer = Customer::factory()->create(['company_name' => 'ClinicPlus']);
    $this->client = User::factory()->forCustomer($this->customer, CustomerUserRole::Owner)->create(['locale' => 'en']);
    $this->project = Project::factory()->forCustomer($this->customer)->create(['name' => 'ClinicFlow']);
});

function openTicket($test, array $overrides = []): SupportTicket
{
    $test->actingAs($test->client)->post(route('portal.support.store'), [
        'subject' => 'Schedule not saving',
        'category' => 'bug',
        'priority' => 'high',
        'project' => $test->project->ulid,
        'body' => 'When we save the schedule it reverts.',
        ...$overrides,
    ])->assertRedirect();

    return SupportTicket::query()->latest('id')->firstOrFail();
}

test('a client opens a ticket; support is notified and it is audited', function () {
    $ticket = openTicket($this);

    expect($ticket->reference)->toBe(sprintf('TK-%d-%05d', now()->year, $ticket->id))
        ->and($ticket->status)->toBe(TicketStatus::Open)
        ->and($ticket->customer_id)->toBe($this->customer->id)
        ->and($ticket->project_id)->toBe($this->project->id)
        ->and($ticket->opened_by)->toBe($this->client->id)
        ->and($ticket->replies()->sole()->from_client)->toBeTrue()
        ->and(AuditLog::query()->where('operation', 'ticket.opened')->exists())->toBeTrue();

    Notification::assertSentTo($this->agent, SupportTicketUpdated::class, fn ($notification, $channels, $notifiable, ?string $locale) => $notification->event === 'opened' && $locale === 'ar');
});

test('ticket validation rejects bad input', function () {
    $this->actingAs($this->client)->post(route('portal.support.store'), ['subject' => '', 'category' => 'nope', 'priority' => 'critical', 'body' => ''])
        ->assertSessionHasErrors(['subject', 'category', 'priority', 'body']);

    expect(SupportTicket::query()->count())->toBe(0);
});

test('the full lifecycle stamps SLA times and hides internal notes from the client', function () {
    $ticket = openTicket($this);

    // Internal note: no SLA stamp, no status change, the client is not told.
    $this->actingAs($this->agent)->post(route('admin.tickets.reply', $ticket), ['body' => 'INTERNAL: probably timezone bug', 'internal' => '1'])->assertRedirect();
    expect($ticket->fresh()->first_response_at)->toBeNull()->and($ticket->fresh()->status)->toBe(TicketStatus::Open);
    Notification::assertNotSentTo($this->client, SupportTicketUpdated::class);

    // Public reply: first response stamped once, waiting on the customer.
    $this->travel(2)->hours();
    $this->actingAs($this->agent)->post(route('admin.tickets.reply', $ticket), ['body' => 'Reproduced, fix ships today.'])->assertRedirect();
    $firstResponse = $ticket->fresh()->first_response_at;
    expect($firstResponse)->not->toBeNull()->and($ticket->fresh()->status)->toBe(TicketStatus::AwaitingCustomer);
    Notification::assertSentTo($this->client, SupportTicketUpdated::class, fn ($notification, $channels, $notifiable, ?string $locale) => $notification->forClient && $locale === 'en');

    $this->actingAs($this->agent)->post(route('admin.tickets.reply', $ticket), ['body' => 'Another reply'])->assertRedirect();
    expect($ticket->fresh()->first_response_at->equalTo($firstResponse))->toBeTrue();

    // The client never sees the internal note in the portal.
    $this->actingAs($this->client)->get(route('portal.support.show', $ticket))
        ->assertSuccessful()
        ->assertSee('Reproduced, fix ships today.')
        ->assertDontSee('INTERNAL: probably timezone bug');
    $this->actingAs($this->agent)->get(route('admin.tickets.show', $ticket))->assertSee('INTERNAL: probably timezone bug');

    // Client reply → awaiting support.
    $this->actingAs($this->client)->post(route('portal.support.reply', $ticket), ['body' => 'Still happening on Branch 4'])->assertRedirect();
    expect($ticket->fresh()->status)->toBe(TicketStatus::AwaitingSupport);

    // Client marks it resolved → resolved_at stamped.
    $this->actingAs($this->client)->post(route('portal.support.resolve', $ticket))->assertRedirect();
    expect($ticket->fresh()->status)->toBe(TicketStatus::Resolved)->and($ticket->fresh()->resolved_at)->not->toBeNull();

    // A client reply reopens a resolved ticket.
    $this->actingAs($this->client)->post(route('portal.support.reply', $ticket), ['body' => 'Sorry, it is back'])->assertRedirect();
    expect($ticket->fresh()->status)->toBe(TicketStatus::AwaitingSupport)->and($ticket->fresh()->resolved_at)->toBeNull();

    // Staff close it; closed tickets take no replies.
    $this->actingAs($this->agent)->put(route('admin.tickets.update', $ticket), ['status' => 'closed', 'priority' => 'urgent', 'assigned_to' => $this->agent->id])->assertRedirect();
    $ticket->refresh();
    expect($ticket->status)->toBe(TicketStatus::Closed)->and($ticket->closed_at)->not->toBeNull()->and($ticket->assigned_to)->toBe($this->agent->id);

    $this->actingAs($this->client)->post(route('portal.support.reply', $ticket), ['body' => 'More'])->assertSessionHasErrors('body');
    $this->actingAs($this->agent)->put(route('admin.tickets.update', $ticket), ['status' => 'open', 'priority' => 'urgent'])->assertSessionHasErrors('status');

    expect(AuditLog::query()->where('auditable_type', 'support_ticket')->where('auditable_id', $ticket->id)->count())->toBeGreaterThanOrEqual(7);
});

test('only support staff can be assigned and only support.manage opens the queue', function () {
    $ticket = SupportTicket::factory()->create(['customer_id' => $this->customer->id, 'subject' => 'Queue item']);
    $editor = User::factory()->role(Role::ContentManager)->create();

    $this->actingAs($this->agent)->get(route('admin.tickets.index'))->assertSuccessful()->assertSee('Queue item');
    $this->actingAs($editor)->get(route('admin.tickets.index'))->assertForbidden();
    $this->actingAs($editor)->get(route('admin.tickets.show', $ticket))->assertForbidden();
    $this->actingAs($editor)->post(route('admin.tickets.reply', $ticket), ['body' => 'x'])->assertForbidden();
    $this->actingAs($this->client)->get(route('admin.tickets.index'))->assertForbidden();

    $this->actingAs($this->agent)->put(route('admin.tickets.update', $ticket), ['status' => 'open', 'priority' => 'low', 'assigned_to' => $editor->id])->assertSessionHasErrors('assigned_to');
});

test('the queue filters by status, priority and assignee', function () {
    SupportTicket::factory()->create(['customer_id' => $this->customer->id, 'subject' => 'Mine and open', 'assigned_to' => $this->agent->id]);
    SupportTicket::factory()->status(TicketStatus::Closed)->create(['customer_id' => $this->customer->id, 'subject' => 'Old closed']);

    $this->actingAs($this->agent)->get(route('admin.tickets.index'))->assertSee('Mine and open')->assertDontSee('Old closed');
    $this->actingAs($this->agent)->get(route('admin.tickets.index', ['status' => 'all']))->assertSee('Old closed');
    $this->actingAs($this->agent)->get(route('admin.tickets.index', ['assignee' => 'none']))->assertDontSee('Mine and open');
    $this->actingAs($this->agent)->get(route('admin.tickets.index', ['assignee' => 'me']))->assertSee('Mine and open');
});

test('resolved tickets close automatically after the configured days', function () {
    config(['codefanz.support.auto_close_after_days' => 7]);

    $stale = SupportTicket::factory()->status(TicketStatus::Resolved)->create(['customer_id' => $this->customer->id, 'resolved_at' => now()->subDays(8)]);
    $recent = SupportTicket::factory()->status(TicketStatus::Resolved)->create(['customer_id' => $this->customer->id, 'resolved_at' => now()->subDays(3)]);
    $open = SupportTicket::factory()->create(['customer_id' => $this->customer->id]);

    $this->artisan('tickets:close-resolved')->expectsOutputToContain('Closed 1')->assertSuccessful();

    expect($stale->fresh()->status)->toBe(TicketStatus::Closed)
        ->and($stale->fresh()->closed_at)->not->toBeNull()
        ->and($recent->fresh()->status)->toBe(TicketStatus::Resolved)
        ->and($open->fresh()->status)->toBe(TicketStatus::Open)
        ->and(AuditLog::query()->where('operation', 'ticket.auto_closed')->where('auditable_id', $stale->id)->value('actor_type')?->value)->toBe('system');
});

test('the auto-close command is scheduled daily', function () {
    $events = collect(app(Schedule::class)->events())->filter(fn ($event) => str_contains((string) $event->command, 'tickets:close-resolved'));

    expect($events)->toHaveCount(1);
});

test('admin ticket screens render in English and Arabic', function () {
    $ticket = openTicket($this);

    foreach (['en' => 'ltr', 'ar' => 'rtl'] as $locale => $direction) {
        $this->agent->forceFill(['locale' => $locale])->save();
        $this->actingAs($this->agent)->get(route('admin.tickets.index'))->assertSuccessful()->assertSee('dir="'.$direction.'"', false);
        $this->actingAs($this->agent)->get(route('admin.tickets.show', $ticket))->assertSuccessful()->assertSee('dir="'.$direction.'"', false)->assertSee($ticket->reference);
    }
});
