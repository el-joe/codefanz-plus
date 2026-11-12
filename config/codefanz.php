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

    'media' => [
        'max_upload_kb' => (int) env('MEDIA_MAX_UPLOAD_KB', 10240),
        'max_dimension' => 8000,
        'max_pixels' => 40_000_000,
        'widths' => [320, 640, 960, 1280, 1920],
        'quality' => ['avif' => 55, 'webp' => 78],
        'public_disk' => env('MEDIA_PUBLIC_DISK', 'public'),
        'private_disk' => env('MEDIA_PRIVATE_DISK', 'local'),
    ],

    'change_requests' => [
        'expires_after_days' => (int) env('CHANGE_REQUEST_EXPIRY_DAYS', 14),
    ],

];
