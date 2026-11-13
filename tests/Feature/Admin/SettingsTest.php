<?php

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\Settings;

beforeEach(function () {
    $this->admin = User::factory()->role(Role::Administrator)->withConfirmedTwoFactor()->create();
});

test('staff without the settings permission cannot open settings', function () {
    $this->actingAs(User::factory()->role(Role::ContentManager)->withConfirmedTwoFactor()->create())
        ->get(route('admin.settings.edit', 'general'))
        ->assertForbidden();
});

test('unknown settings groups are not found', function () {
    $this->actingAs($this->admin)->get('/admin/settings/secrets')->assertNotFound();
});

test('general settings are saved per language and audited', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.settings.update', 'general'), ['settings' => [
            'company_name' => ['en' => 'CodeFanz', 'ar' => 'كود فانز'],
            'tagline' => ['en' => 'Digital products', 'ar' => ''],
        ]])
        ->assertRedirect(route('admin.settings.edit', 'general'));

    $settings = app(Settings::class);

    expect($settings->get('general', 'company_name'))->toBe(['en' => 'CodeFanz', 'ar' => 'كود فانز'])
        ->and($settings->get('general', 'tagline'))->toBe(['en' => 'Digital products'])
        ->and(AuditLog::query()->where('operation', 'settings.general.updated')->exists())->toBeTrue();

    $this->actingAs($this->admin)->get(route('admin.settings.edit', 'general'))->assertSee('كود فانز');
});

test('contact details are validated', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.settings.update', 'contact'), ['settings' => ['email' => 'not-an-email', 'whatsapp' => 'call me']])
        ->assertSessionHasErrors(['settings.email', 'settings.whatsapp']);
});

test('secrets are encrypted, masked and never echoed or audited in plain text', function () {
    $secret = 'sk-ant-test-1234567890abcd';

    $this->actingAs($this->admin)
        ->put(route('admin.settings.update', 'integrations'), ['settings' => ['anthropic_api_key' => $secret]])
        ->assertSessionHasNoErrors();

    $stored = Setting::query()->where('key', 'anthropic_api_key')->sole();

    expect($stored->is_encrypted)->toBeTrue()
        ->and($stored->value)->not->toContain($secret)
        ->and(app(Settings::class)->get('integrations', 'anthropic_api_key'))->toBe($secret)
        ->and(AuditLog::query()->where('operation', 'settings.integrations.updated')->sole()->after)->not->toContain($secret);

    $this->actingAs($this->admin)->get(route('admin.settings.edit', 'integrations'))
        ->assertSuccessful()
        ->assertDontSee($secret)
        ->assertSee('••••abcd')
        ->assertSee(__('Credentials saved · not verified'));
});

test('a blank secret keeps the stored value and clearing removes it', function () {
    $settings = app(Settings::class);
    $settings->set('integrations', 'anthropic_api_key', 'sk-ant-keep-me', encrypted: true);

    $this->actingAs($this->admin)->put(route('admin.settings.update', 'integrations'), ['settings' => ['anthropic_api_key' => '']]);
    expect($settings->get('integrations', 'anthropic_api_key'))->toBe('sk-ant-keep-me');

    $this->actingAs($this->admin)->put(route('admin.settings.update', 'integrations'), ['settings' => [], 'clear' => ['anthropic_api_key']]);
    expect($settings->get('integrations', 'anthropic_api_key'))->toBeNull();
});

test('integrations show honest statuses', function () {
    app(Settings::class)->set('integrations', 'ga4_measurement_id', 'G-ABC1234');

    $this->actingAs($this->admin)->get(route('admin.settings.edit', 'integrations'))
        ->assertSee(__('Incomplete'))
        ->assertSee(__('Requires credentials'))
        ->assertDontSee('Live');
});

test('a malformed GA4 measurement id is rejected', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.settings.update', 'integrations'), ['settings' => ['ga4_measurement_id' => 'UA-123']])
        ->assertSessionHasErrors('settings.ga4_measurement_id');
});
