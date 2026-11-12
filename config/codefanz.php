<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Languages
    |--------------------------------------------------------------------------
    |
    | Fallback list used before the languages table is seeded. The database is
    | the source of truth once seeded (Admin → Settings → Languages).
    |
    */

    'languages' => [
        ['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'direction' => 'ltr', 'is_default' => true],
        ['code' => 'ar', 'name' => 'Arabic', 'native_name' => 'العربية', 'direction' => 'rtl', 'is_default' => false],
    ],

    /*
    |--------------------------------------------------------------------------
    | Governance
    |--------------------------------------------------------------------------
    */

    'change_requests' => [
        'expires_after_days' => (int) env('CHANGE_REQUEST_EXPIRY_DAYS', 14),
    ],

];
