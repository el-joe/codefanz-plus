<?php

use App\Enums\CustomerUserRole;
use App\Enums\Role;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\User;

/**
 * Structural WCAG 2.2 AA guarantees every public template must keep (M11.2). Colour contrast, focus and keyboard
 * behaviour are audited in the browser (docs/QA_CHECKLIST.md); these checks catch regressions in the markup.
 */

/**
 * The accessible name an element gets from aria-label, aria-labelledby, its text or the alt of an image inside it.
 */
function accessibleName(DOMXPath $xpath, DOMElement $element): string
{
    if (trim($element->getAttribute('aria-label')) !== '') {
        return trim($element->getAttribute('aria-label'));
    }

    if ($element->getAttribute('aria-labelledby') !== '') {
        return collect(explode(' ', $element->getAttribute('aria-labelledby')))
            ->map(fn (string $id): string => trim($xpath->query('//*[@id="'.$id.'"]')->item(0)?->textContent ?? ''))
            ->implode(' ');
    }

    $alts = collect(iterator_to_array($xpath->query('.//img[@alt] | .//*[@aria-label]', $element)))
        ->map(fn (DOMElement $child): string => $child->getAttribute('alt') ?: $child->getAttribute('aria-label'))
        ->implode(' ');

    return trim(preg_replace('/\s+/u', ' ', $element->textContent.' '.$alts) ?? '');
}

test('public pages expose language, direction, landmarks, a skip link, one h1, labels, names and alt text', function (string $route) {
    $paths = publicSiteRoutes()[$route];

    foreach (['en' => 'ltr', 'ar' => 'rtl'] as $locale => $direction) {
        $html = $this->get($paths[$locale])->assertOk()->getContent();
        $xpath = htmlXPath($html);
        $root = $xpath->query('/html')->item(0);

        expect($root->getAttribute('lang'))->toBe($locale)
            ->and($root->getAttribute('dir'))->toBe($direction)
            ->and($xpath->query('//main[@id="main"]')->length)->toBe(1, "{$route} ({$locale}) needs one <main id=\"main\">")
            ->and($xpath->query('//header')->length)->toBeGreaterThanOrEqual(1)
            ->and($xpath->query('//footer')->length)->toBeGreaterThanOrEqual(1)
            ->and($xpath->query('//h1')->length)->toBe(1, "{$route} ({$locale}) must have exactly one h1")
            ->and(trim($xpath->query('//title')->item(0)?->textContent ?? ''))->not->toBe('');

        $firstLink = $xpath->query('//body//a[@href]')->item(0);
        expect($firstLink?->getAttribute('href'))->toBe('#main', "{$route} ({$locale}) must start with a skip link");

        // Headings never skip a level when going deeper (h2 → h4), so the outline stays navigable.
        $previous = 0;
        foreach ($xpath->query('//main//h1 | //main//h2 | //main//h3 | //main//h4 | //main//h5 | //main//h6') as $heading) {
            $level = (int) substr($heading->nodeName, 1);
            expect($level - $previous)->toBeLessThanOrEqual(1, "{$route} ({$locale}) jumps from h{$previous} to h{$level}: ".trim($heading->textContent));
            $previous = $level;
        }

        // Toasts and other status messages are announced (WCAG 4.1.3).
        expect($xpath->query('//*[@data-toast-region][@aria-live="polite"]')->length)->toBe(1);

        foreach ($xpath->query('//img') as $image) {
            expect($image->hasAttribute('alt'))->toBeTrue("{$route} ({$locale}): <img src=\"{$image->getAttribute('src')}\"> has no alt");
        }

        foreach ($xpath->query('//input[not(@type="hidden") and not(@type="submit") and not(@type="button")] | //select | //textarea') as $control) {
            $id = $control->getAttribute('id');
            $labelled = ($id !== '' && $xpath->query('//label[@for="'.$id.'"]')->length > 0)
                || $xpath->query('ancestor::label', $control)->length > 0
                || trim($control->getAttribute('aria-label')) !== ''
                || $control->getAttribute('aria-labelledby') !== '';

            expect($labelled)->toBeTrue("{$route} ({$locale}): <{$control->nodeName} name=\"{$control->getAttribute('name')}\"> has no label");

            foreach (array_filter(explode(' ', $control->getAttribute('aria-describedby'))) as $describedBy) {
                expect($xpath->query('//*[@id="'.$describedBy.'"]')->length)->toBe(1, "{$route} ({$locale}): aria-describedby=\"{$describedBy}\" points nowhere");
            }
        }

        foreach ($xpath->query('//a[@href] | //button | //summary') as $control) {
            if ($xpath->query('ancestor-or-self::*[@aria-hidden="true"]', $control)->length > 0) {
                continue;
            }

            expect(accessibleName($xpath, $control))->not->toBe('', "{$route} ({$locale}): a <{$control->nodeName}> has no accessible name");
        }

        $ids = collect(iterator_to_array($xpath->query('//*[@id]')))->map(fn (DOMElement $element): string => $element->getAttribute('id'));
        expect($ids->duplicates()->values()->all())->toBe([], "{$route} ({$locale}) repeats ids");
    }
})->with(publicSiteRouteNames());

test('form errors are tied to their fields and announced', function () {
    $this->from('/en/contact')->post(route('contact.store', ['locale' => 'en']), [])->assertRedirect('/en/contact');

    $xpath = htmlXPath($this->get('/en/contact')->getContent());
    $email = $xpath->query('//input[@name="email"]')->item(0);

    expect($email->getAttribute('aria-invalid'))->toBe('true')
        ->and($email->getAttribute('aria-describedby'))->not->toBe('');

    foreach (array_filter(explode(' ', $email->getAttribute('aria-describedby'))) as $id) {
        expect($xpath->query('//*[@id="'.$id.'"]')->length)->toBe(1);
    }
});

test('auth pages have labelled fields with autocomplete hints', function (string $path, array $autocomplete) {
    $xpath = htmlXPath($this->get($path)->assertOk()->getContent());

    expect($xpath->query('/html')->item(0)->getAttribute('lang'))->not->toBe('')
        ->and($xpath->query('//h1')->length)->toBe(1)
        ->and($xpath->query('//main[@id="main"]')->length)->toBe(1);

    foreach ($autocomplete as $name => $hint) {
        $input = $xpath->query('//input[@name="'.$name.'"]')->item(0);
        expect($input?->getAttribute('autocomplete'))->toBe($hint)
            ->and($xpath->query('//label[@for="'.$input->getAttribute('id').'"]')->length)->toBe(1);
    }
})->with([
    'login' => ['/login', ['email' => 'username', 'password' => 'current-password']],
    'forgot password' => ['/forgot-password', ['email' => 'username']],
]);

test('the stylesheet keeps focused elements clear of the sticky header and honours reduced motion', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    expect($css)->toContain('scroll-padding-top')
        ->toContain('prefers-reduced-motion: reduce')
        ->toContain(':focus-visible');
});

test('admin and portal screens have labelled landmarks, one main, no nested complementary regions and a stateful theme toggle', function (string $area) {
    if ($area === 'admin') {
        Lead::factory()->count(30)->create();
        $this->actingAs(User::factory()->role(Role::SuperAdmin)->withConfirmedTwoFactor()->create());
        $paths = [route('admin.dashboard'), route('admin.leads.index'), route('admin.leads.board'), route('admin.leads.create'), route('admin.users.create')];
    } else {
        $customer = Customer::factory()->create();
        $this->actingAs(User::factory()->forCustomer($customer, CustomerUserRole::Owner)->create(['locale' => 'ar']));
        $paths = [route('portal.dashboard'), route('portal.profile'), route('portal.documents.index')];
    }

    foreach ($paths as $path) {
        $xpath = htmlXPath($this->get($path)->assertOk()->getContent());

        expect($xpath->query('//main[@id="main"]')->length)->toBe(1, $path)
            ->and($xpath->query('//body//a[@href]')->item(0)?->getAttribute('href'))->toBe('#main', $path)
            ->and($xpath->query('//aside[not(@aria-label) and not(@aria-labelledby)]')->length)->toBe(0, "{$path}: unlabelled aside")
            ->and($xpath->query('//main//aside[not(@role="note")]')->length)->toBe(0, "{$path}: complementary landmark inside main")
            ->and($xpath->query('//button[@data-theme-toggle]')->length)->toBeGreaterThan(0)
            ->and($xpath->query('//button[@data-theme-toggle][not(@aria-pressed)]')->length)->toBe(0, "{$path}: theme toggle without aria-pressed")
            // Disabled pagination arrows carry their label on an element with a role (WCAG 4.1.2).
            ->and($xpath->query('//span[@aria-label and not(@role)]')->length)->toBe(0, "{$path}: aria-label on a span without a role");

        $ids = collect(iterator_to_array($xpath->query('//*[@id]')))->map(fn (DOMElement $element): string => $element->getAttribute('id'));
        expect($ids->duplicates()->values()->all())->toBe([], "{$path} repeats ids");
    }
})->with(['admin', 'portal']);
