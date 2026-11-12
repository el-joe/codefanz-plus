<?php

use App\Models\Language;
use App\Services\Settings;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    config(['cache.default' => 'database']);
    Cache::flush();
});

test('active languages survive a round trip through the database cache store', function () {
    Language::active();

    $cached = Language::active();

    expect($cached->keys()->all())->toBe(['en', 'ar'])
        ->and($cached['ar']['direction'])->toBe('rtl');
});

test('encrypted settings survive a round trip through the database cache store', function () {
    $settings = app(Settings::class);
    $settings->set('ai', 'openai_key', 'sk-test-1234abcd', encrypted: true);

    $settings->get('ai', 'openai_key');

    expect($settings->get('ai', 'openai_key'))->toBe('sk-test-1234abcd')
        ->and($settings->masked('ai', 'openai_key'))->toBe('••••abcd');
});
