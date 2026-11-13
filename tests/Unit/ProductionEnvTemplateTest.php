<?php

/**
 * deploy/production/.env.production.example must list every variable the application reads, and must not ship
 * development settings or secrets.
 */
function productionEnvTemplate(): string
{
    return (string) file_get_contents(dirname(__DIR__, 2).'/deploy/production/.env.production.example');
}

test('every env() variable read by the application is listed in the production template', function () {
    $root = dirname(__DIR__, 2);
    $sources = [...glob($root.'/config/*.php'), ...glob($root.'/bootstrap/*.php'), ...glob($root.'/routes/*.php')];
    $read = [];

    foreach ($sources as $file) {
        preg_match_all("/env\\(\\s*['\"]([A-Z0-9_]+)['\"]/", (string) file_get_contents($file), $matches);
        $read = [...$read, ...$matches[1]];
    }

    preg_match_all('/^(?:#\s*)?([A-Z0-9_]+)=/m', productionEnvTemplate(), $listed);
    $missing = array_values(array_diff(array_unique($read), $listed[1]));

    expect($read)->not->toBeEmpty()
        ->and($missing)->toBe([]);
});

test('the production template uses secure production values', function () {
    $template = productionEnvTemplate();

    expect($template)
        ->toContain("\nAPP_ENV=production\n")
        ->toContain("\nAPP_DEBUG=false\n")
        ->toContain("\nAPP_URL=https://")
        ->toContain("\nSESSION_SECURE_COOKIE=true\n")
        ->toContain("\nSESSION_ENCRYPT=true\n")
        ->toContain("\nALLOW_PRIVATE_OUTBOUND=false\n")
        ->toContain("\nCHECK_BREACHED_PASSWORDS=true\n")
        ->toContain("\nDB_QUEUE_RETRY_AFTER=1900\n")
        ->not->toMatch('/^QUEUE_CONNECTION=sync/m')
        ->not->toMatch('/^MAIL_MAILER=(log|array)/m')
        ->not->toMatch('/^CACHE_STORE=array/m')
        ->not->toMatch('/^(APP_KEY|DB_PASSWORD|MAIL_PASSWORD|HEALTH_TOKEN|DEV_[A-Z_]+)=.+/m');
});
