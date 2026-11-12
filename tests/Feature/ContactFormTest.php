<?php

use App\Enums\ContactTopic;
use App\Enums\Role;
use App\Http\Requests\ContactMessageRequest;
use App\Models\AuditLog;
use App\Models\ContactMessage;
use App\Models\User;
use App\Notifications\ContactMessageReceived;
use App\Services\Settings;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    app(Settings::class)->set('contact', 'email', 'team@example.com');
});

/**
 * @return array<string, mixed>
 */
function contactPayload(array $overrides = []): array
{
    return [
        'name' => 'Mona Hassan',
        'email' => 'Mona@Example.com',
        'phone' => '+20 100 000 0000',
        'topic' => 'quote',
        'message' => 'We need a booking platform for three clinics.',
        'preferred_locale' => 'ar',
        'consent' => '1',
        ContactMessageRequest::TIMESTAMP => Crypt::encryptString((string) now()->subSeconds(30)->getTimestamp()),
        ...$overrides,
    ];
}

test('a visitor sends a message that is stored, audited and emailed to the team', function () {
    $this->post('/en/contact', contactPayload())
        ->assertRedirect(route('contact', ['locale' => 'en']))
        ->assertSessionHas('contact_sent');

    $message = ContactMessage::query()->sole();

    expect($message->email)->toBe('mona@example.com')
        ->and($message->topic)->toBe(ContactTopic::Quote)
        ->and($message->preferred_locale)->toBe('ar')
        ->and($message->locale)->toBe('en')
        ->and($message->consented_at)->not->toBeNull()
        ->and(AuditLog::query()->where('operation', 'contact_message.received')->where('auditable_id', $message->id)->exists())->toBeTrue()
        ->and(AuditLog::query()->where('operation', 'contact_message.received')->value('after'))->not->toHaveKey('email');

    Notification::assertSentOnDemand(
        ContactMessageReceived::class,
        fn (ContactMessageReceived $notification, array $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === 'team@example.com'
            && $notification->contactMessage->is($message),
    );

    $this->get('/en/contact')->assertSee('data-contact-sent', false);
});

test('the notification mail carries the message and a reply-to address', function () {
    $message = ContactMessage::factory()->create(['email' => 'client@example.com', 'message' => 'Hello there team']);
    $mail = (new ContactMessageReceived($message))->toMail(new AnonymousNotifiable);

    expect($mail->replyTo[0][0])->toBe('client@example.com')
        ->and($mail->introLines)->toContain('Hello there team')
        ->and($mail->actionUrl)->toBe(route('admin.contact-messages.show', $message));
});

test('the message is still stored when no contact email is configured', function () {
    app(Settings::class)->forget('contact', 'email');

    $this->post('/en/contact', contactPayload())->assertRedirect();

    expect(ContactMessage::query()->count())->toBe(1);
    Notification::assertNothingSent();
});

test('contact form input is validated', function (array $overrides, string $field) {
    $this->from('/en/contact')->post('/en/contact', contactPayload($overrides))
        ->assertRedirect('/en/contact')
        ->assertSessionHasErrors($field);

    expect(ContactMessage::query()->count())->toBe(0);
})->with([
    'missing name' => [['name' => ''], 'name'],
    'invalid email' => [['email' => 'not-an-email'], 'email'],
    'invalid phone' => [['phone' => 'call me'], 'phone'],
    'unknown topic' => [['topic' => 'hacking'], 'topic'],
    'short message' => [['message' => 'Hi'], 'message'],
    'long message' => [['message' => str_repeat('a', 2001)], 'message'],
    'unsupported language' => [['preferred_locale' => 'fr'], 'preferred_locale'],
    'no consent' => [['consent' => null], 'consent'],
]);

test('honeypot and too-fast submissions are discarded silently', function (array $overrides) {
    $this->post('/en/contact', contactPayload($overrides))
        ->assertRedirect(route('contact', ['locale' => 'en']))
        ->assertSessionHas('contact_sent');

    expect(ContactMessage::query()->count())->toBe(0);
    Notification::assertNothingSent();
})->with([
    'honeypot filled' => [[ContactMessageRequest::HONEYPOT => 'https://spam.example']],
    'submitted instantly' => [fn () => [ContactMessageRequest::TIMESTAMP => Crypt::encryptString((string) now()->getTimestamp())]],
    'tampered token' => [[ContactMessageRequest::TIMESTAMP => 'forged']],
]);

test('an expired form asks the visitor to send again', function () {
    $this->from('/en/contact')->post('/en/contact', contactPayload([
        ContactMessageRequest::TIMESTAMP => Crypt::encryptString((string) now()->subHours(3)->getTimestamp()),
    ]))->assertSessionHasErrors('form');

    expect(ContactMessage::query()->count())->toBe(0);
});

test('the contact form is rate limited per visitor', function () {
    foreach (range(1, 3) as $attempt) {
        $this->post('/en/contact', contactPayload())->assertRedirect();
    }

    $this->post('/en/contact', contactPayload())->assertTooManyRequests();

    expect(ContactMessage::query()->count())->toBe(3);
});

test('staff with leads.view read the inbox; others are forbidden', function () {
    $message = ContactMessage::factory()->create(['name' => 'Inbox Visitor', 'message' => 'A message for the sales team.']);

    $sales = User::factory()->role(Role::SalesManager)->create();
    $this->actingAs($sales)->get(route('admin.contact-messages.index'))->assertSuccessful()->assertSee('Inbox Visitor');
    $this->actingAs($sales)->get(route('admin.contact-messages.show', $message))->assertSuccessful()->assertSee('A message for the sales team.');
    $this->actingAs($sales)->get(route('admin.contact-messages.index', ['topic' => 'demo']))->assertSuccessful()->assertDontSee('Inbox Visitor');

    $editor = User::factory()->role(Role::ContentManager)->create();
    $this->actingAs($editor)->get(route('admin.contact-messages.index'))->assertForbidden();
    $this->actingAs($editor)->get(route('admin.contact-messages.show', $message))->assertForbidden();
});
