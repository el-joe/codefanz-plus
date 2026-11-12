<?php

test('the arabic home page renders right to left', function () {
    $this->get('/ar')
        ->assertSuccessful()
        ->assertSee('<html lang="ar" dir="rtl">', false)
        ->assertSee('نحوّل أفكارك إلى');
});

test('the english home page renders left to right with hreflang alternates', function () {
    $this->get('/en')
        ->assertSuccessful()
        ->assertSee('<html lang="en" dir="ltr">', false)
        ->assertSee('hreflang="ar"', false);
});

test('an unsupported locale returns not found', function () {
    $this->get('/fr')->assertNotFound();
});

test('the root redirect honours the browser language', function () {
    $this->withHeader('Accept-Language', 'ar-EG,ar;q=0.9')->get('/')->assertRedirect('/ar');
});

test('switching language stores the preference for non-prefixed pages', function () {
    $this->post(route('locale.switch', 'ar'))->assertRedirect();

    $this->get(route('login'))->assertSee('dir="rtl"', false);
});
