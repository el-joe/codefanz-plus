<?php

use App\Enums\LeadActivityType;
use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\Role;
use App\Events\LeadCreated;
use App\Http\Requests\QuoteRequest;
use App\Models\AuditLog;
use App\Models\Lead;
use App\Models\LeadAttachment;
use App\Models\Service;
use App\Models\Technology;
use App\Models\User;
use App\Notifications\LeadReceivedConfirmation;
use App\Notifications\NewLeadReceived;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Notification::fake();
    Storage::fake(config('codefanz.leads.attachments.disk'));

    $this->service = Service::factory()->withTranslation('en', ['title' => 'Web Development', 'slug' => 'web-development'])
        ->withTranslation('ar', ['title' => 'تطوير الويب', 'slug' => 'تطوير-الويب'])->create();
    $this->technology = Technology::factory()->withTranslation('en', ['name' => 'Laravel'])->create();
});

/**
 * @return array<string, mixed>
 */
function quotePayload(array $overrides = []): array
{
    return [
        'name' => 'Mona Hassan',
        'email' => 'Mona@Example.com',
        'phone' => '+20 100 000 0000',
        'country' => 'EG',
        'company' => 'Clinic Co.',
        'services' => [Service::query()->value('id')],
        'platforms' => ['web', 'android'],
        'description' => 'A booking platform for three clinics with online payments.',
        'features' => "Booking\nPayments\nReports",
        'goals' => 'Fewer phone bookings.',
        'technologies' => [Technology::query()->value('id')],
        'existing_url' => 'https://clinic.example.com',
        'budget_range' => '10k_25k',
        'timeline' => '1_3_months',
        'deadline' => now()->addMonths(3)->toDateString(),
        'preferred_contact' => 'whatsapp',
        'preferred_locale' => 'ar',
        'consent' => '1',
        'utm_source' => 'newsletter',
        QuoteRequest::TIMESTAMP => Crypt::encryptString((string) now()->subMinute()->getTimestamp()),
        ...$overrides,
    ];
}

/**
 * An upload backed by a real temporary file, so the MIME type is detected from its content.
 */
function realUpload(string $name, string $content): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'upl');
    file_put_contents($path, $content);

    return new UploadedFile($path, $name, null, null, true);
}

test('the quote form renders four steps in english and arabic with the service prefilled', function (string $locale, string $dir, string $slug, string $heading) {
    $this->get("/{$locale}/quote?service=".urlencode($slug).'&utm_source=ads')
        ->assertSuccessful()
        ->assertSee('dir="'.$dir.'"', false)
        ->assertSee($heading)
        ->assertSee('data-stepper', false)
        ->assertSee('data-step="4"', false)
        ->assertSee('name="services[]" value="'.$this->service->id.'" checked', false)
        ->assertSee('name="utm_source" value="ads"', false)
        ->assertSee('enctype="multipart/form-data"', false);
})->with([
    'english' => ['en', 'ltr', 'web-development', 'What happens next'],
    'arabic' => ['ar', 'rtl', 'تطوير-الويب', 'ماذا يحدث بعد ذلك'],
]);

test('a visitor submits a quote request with attachments that becomes a lead', function (string $locale) {
    Event::fake([LeadCreated::class]);
    $manager = User::factory()->role(Role::SalesManager)->create();
    $viewer = User::factory()->role(Role::ProjectManager)->create();

    $this->post("/{$locale}/quote", quotePayload([
        'attachments' => [
            UploadedFile::fake()->create('brief.pdf', 200, 'application/pdf'),
            UploadedFile::fake()->image('wireframe.png'),
        ],
    ]))->assertRedirect(route('quote', ['locale' => $locale]))->assertSessionHas('quote_sent');

    $lead = Lead::query()->with(['services', 'technologies', 'attachments', 'activities'])->sole();

    expect($lead->source)->toBe(LeadSource::QuoteForm)
        ->and($lead->status)->toBe(LeadStatus::New)
        ->and($lead->email)->toBe('mona@example.com')
        ->and($lead->reference)->toMatch('/^LD-\d{4}-\d{5}$/')
        ->and($lead->locale)->toBe($locale)
        ->and($lead->platforms)->toBe(['web', 'android'])
        ->and($lead->utm_source)->toBe('newsletter')
        ->and($lead->consent_at)->not->toBeNull()
        ->and($lead->services->pluck('id')->all())->toBe([$this->service->id])
        ->and($lead->technologies->pluck('id')->all())->toBe([$this->technology->id])
        ->and($lead->attachments)->toHaveCount(2)
        ->and($lead->activities->first()->type)->toBe(LeadActivityType::System);

    foreach ($lead->attachments as $attachment) {
        Storage::disk($attachment->disk)->assertExists($attachment->path);
        expect($attachment->path)->not->toContain('brief')->not->toContain('wireframe');
    }

    expect($lead->attachments->pluck('original_name')->sort()->values()->all())->toBe(['brief.pdf', 'wireframe.png']);

    $audit = AuditLog::query()->where('operation', 'lead.created')->sole();
    expect($audit->auditable_id)->toBe($lead->id)
        ->and($audit->after)->not->toHaveKey('email')
        ->and($audit->after['attachments'])->toBe(2);

    Event::assertDispatched(LeadCreated::class, fn (LeadCreated $event) => $event->lead->is($lead));
    Notification::assertSentTo($manager, NewLeadReceived::class);
    Notification::assertNotSentTo($viewer, NewLeadReceived::class);
    Notification::assertSentOnDemand(
        LeadReceivedConfirmation::class,
        fn (LeadReceivedConfirmation $notification, array $channels, AnonymousNotifiable $notifiable) => array_key_first($notifiable->routes['mail']) === 'mona@example.com'
            && $notification->locale === 'ar',
    );

    $this->get("/{$locale}/quote")->assertSee('data-quote-sent', false)->assertSee($lead->reference);
})->with(['en', 'ar']);

test('the auto-reply is written in the visitor’s language and does not echo the request', function () {
    $lead = Lead::factory()->create(['preferred_locale' => 'ar', 'description' => 'Secret project details']);
    $lead->refresh();

    app()->setLocale('ar');
    $rendered = (new LeadReceivedConfirmation($lead))->toMail(new AnonymousNotifiable);

    expect($rendered->subject)->toContain($lead->reference)
        ->and($rendered->subject)->toContain('استلمنا')
        ->and(implode(' ', $rendered->introLines))->not->toContain('Secret project details');
});

test('each step’s fields are validated on the final submit', function (array $overrides, string $field) {
    $this->from('/en/quote')->post('/en/quote', quotePayload($overrides))
        ->assertRedirect('/en/quote')
        ->assertSessionHasErrors($field);

    expect(Lead::query()->count())->toBe(0);
})->with([
    'step 1: missing name' => [['name' => ''], 'name'],
    'step 1: invalid email' => [['email' => 'nope'], 'email'],
    'step 1: missing phone' => [['phone' => ''], 'phone'],
    'step 1: unknown country' => [['country' => 'XX'], 'country'],
    'step 2: no service' => [['services' => []], 'services'],
    'step 2: unpublished service' => [fn () => ['services' => [Service::factory()->draft()->create()->id]], 'services.0'],
    'step 2: no platform' => [['platforms' => []], 'platforms'],
    'step 2: unknown platform' => [['platforms' => ['fridge']], 'platforms.0'],
    'step 2: short description' => [['description' => 'Too short'], 'description'],
    'step 2: missing features' => [['features' => ''], 'features'],
    'step 2: bad website' => [['existing_url' => 'javascript:alert(1)'], 'existing_url'],
    'step 3: unknown budget' => [['budget_range' => 'millions'], 'budget_range'],
    'step 3: past deadline' => [['deadline' => '2020-01-01'], 'deadline'],
    'step 4: no contact method' => [['preferred_contact' => 'pigeon'], 'preferred_contact'],
    'step 4: unsupported language' => [['preferred_locale' => 'fr'], 'preferred_locale'],
    'step 4: no consent' => [['consent' => null], 'consent'],
]);

test('the form reopens on the first step that has an error', function () {
    $this->from('/en/quote')->post('/en/quote', quotePayload(['budget_range' => 'millions']));

    $this->get('/en/quote')->assertSee('data-initial-step="3"', false);
});

test('attachments are limited by count, type and size', function (array $files, string $field) {
    $this->from('/en/quote')->post('/en/quote', quotePayload(['attachments' => $files]))
        ->assertSessionHasErrors($field);

    expect(Lead::query()->count())->toBe(0)
        ->and(Storage::disk(config('codefanz.leads.attachments.disk'))->allFiles())->toBeEmpty();
})->with([
    'too many files' => [fn () => array_map(fn ($i) => UploadedFile::fake()->create("f{$i}.pdf", 10, 'application/pdf'), range(1, 6)), 'attachments'],
    'executable' => [fn () => [UploadedFile::fake()->create('run.exe', 10, 'application/x-msdownload')], 'attachments.0'],
    'php disguised as pdf' => [fn () => [realUpload('invoice.pdf', '<?php echo "hi";')], 'attachments.0'],
    'pdf renamed to html' => [fn () => [UploadedFile::fake()->create('page.html', 10, 'application/pdf')], 'attachments.0'],
    'too large' => [fn () => [UploadedFile::fake()->create('huge.pdf', config('codefanz.leads.attachments.max_kb') + 1, 'application/pdf')], 'attachments.0'],
]);

test('honeypot and too-fast quote submissions are discarded silently', function (array $overrides) {
    $this->post('/en/quote', quotePayload($overrides))
        ->assertRedirect(route('quote', ['locale' => 'en']))
        ->assertSessionHas('quote_sent');

    expect(Lead::query()->count())->toBe(0);
    Notification::assertNothingSent();
})->with([
    'honeypot filled' => [[QuoteRequest::HONEYPOT => 'https://spam.example']],
    'submitted instantly' => [fn () => [QuoteRequest::TIMESTAMP => Crypt::encryptString((string) now()->getTimestamp())]],
    'tampered token' => [[QuoteRequest::TIMESTAMP => 'forged']],
]);

test('an expired quote form asks the visitor to send again', function () {
    $this->from('/en/quote')->post('/en/quote', quotePayload([
        QuoteRequest::TIMESTAMP => Crypt::encryptString((string) now()->subHours(3)->getTimestamp()),
    ]))->assertSessionHasErrors('form');

    expect(Lead::query()->count())->toBe(0);
});

test('the quote form is rate limited per visitor', function () {
    foreach (range(1, 3) as $attempt) {
        $this->post('/en/quote', quotePayload(['email' => "visitor{$attempt}@example.com"]))->assertRedirect();
    }

    $this->post('/en/quote', quotePayload())->assertTooManyRequests();

    expect(Lead::query()->count())->toBe(3);
});

test('a second request from the same email is flagged as a possible duplicate, not merged', function () {
    $first = Lead::factory()->create(['email' => 'mona@example.com']);

    $this->post('/en/quote', quotePayload())->assertRedirect();

    $second = Lead::query()->whereKeyNot($first->id)->sole();
    expect($second->possible_duplicate_of_id)->toBe($first->id)
        ->and(Lead::query()->count())->toBe(2);
});

test('closed leads do not trigger the duplicate hint', function () {
    Lead::factory()->status(LeadStatus::Lost)->create(['email' => 'mona@example.com']);

    $this->post('/en/quote', quotePayload())->assertRedirect();

    expect(Lead::query()->latest('id')->first()->possible_duplicate_of_id)->toBeNull();
});

test('attachments are never publicly reachable', function () {
    $this->post('/en/quote', quotePayload(['attachments' => [UploadedFile::fake()->create('brief.pdf', 20, 'application/pdf')]]));

    $attachment = LeadAttachment::query()->sole();

    expect($attachment->disk)->not->toBe('public');
    Storage::disk('public')->assertMissing($attachment->path);
    // The private disk's local "serve" route only answers to signed URLs; nothing else is exposed.
    expect($this->get('/storage/'.$attachment->path)->status())->toBeIn([403, 404])
        ->and($this->get('/'.$attachment->path)->status())->toBe(404);
});

test('quote calls to action point at the quote form', function () {
    $this->get('/en/services/web-development')
        ->assertSuccessful()
        ->assertSee(route('quote', ['locale' => 'en', 'service' => 'web-development']), false);
});
