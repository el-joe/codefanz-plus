<?php

test('the application root redirects to the localized home page', function () {
    $this->get('/')->assertRedirect('/en');

    $this->get('/en')->assertSuccessful();
});
