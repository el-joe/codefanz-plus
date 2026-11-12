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

    /*
    |--------------------------------------------------------------------------
    | Business settings (Admin → Settings)
    |--------------------------------------------------------------------------
    |
    | Field types: text, email, url, translatable (one value per active
    | language) and secret (encrypted at rest, never shown again, blank keeps
    | the stored value). Values live in the settings table, not here.
    |
    */

    'settings' => [
        'general' => [
            'company_name' => ['type' => 'translatable', 'label' => 'Company name', 'rules' => ['nullable', 'string', 'max:120']],
            'tagline' => ['type' => 'translatable', 'label' => 'Tagline', 'rules' => ['nullable', 'string', 'max:160']],
        ],
        'contact' => [
            'email' => ['type' => 'email', 'label' => 'Contact email', 'rules' => ['nullable', 'email', 'max:255']],
            'phone' => ['type' => 'text', 'label' => 'Phone', 'rules' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9 ()-]{6,30}$/']],
            'whatsapp' => ['type' => 'text', 'label' => 'WhatsApp number', 'rules' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9]{6,20}$/']],
            'address' => ['type' => 'translatable', 'label' => 'Address', 'rules' => ['nullable', 'string', 'max:255']],
        ],
        'integrations' => [
            'ga4_measurement_id' => ['type' => 'text', 'label' => 'GA4 measurement ID', 'rules' => ['nullable', 'string', 'regex:/^G-[A-Z0-9]{4,20}$/']],
            'ga4_api_secret' => ['type' => 'secret', 'label' => 'GA4 Measurement Protocol API secret', 'rules' => ['nullable', 'string', 'max:255']],
            'anthropic_api_key' => ['type' => 'secret', 'label' => 'Anthropic API key', 'rules' => ['nullable', 'string', 'max:255']],
        ],
    ],

    /*
    | Integration status is derived from saved credentials only. A provider is
    | never shown as "Live" until a connection test exists for it.
    */

    'integrations' => [
        'google_analytics' => ['label' => 'Google Analytics 4', 'keys' => ['ga4_measurement_id', 'ga4_api_secret']],
        'anthropic' => ['label' => 'Anthropic (AI Content Studio)', 'keys' => ['anthropic_api_key']],
    ],

    'change_requests' => [
        'expires_after_days' => (int) env('CHANGE_REQUEST_EXPIRY_DAYS', 14),
    ],

];
