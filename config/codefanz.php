<?php

use App\Services\Payments\StripeGateway;

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
            'hours' => ['type' => 'translatable', 'label' => 'Office hours', 'rules' => ['nullable', 'string', 'max:120']],
            'meeting_url' => ['type' => 'url', 'label' => 'Meeting booking link (https)', 'rules' => ['nullable', 'url:https', 'max:500']],
        ],
        'integrations' => [
            'ga4_measurement_id' => ['type' => 'text', 'label' => 'GA4 measurement ID', 'rules' => ['nullable', 'string', 'regex:/^G-[A-Z0-9]{4,20}$/']],
            'ga4_api_secret' => ['type' => 'secret', 'label' => 'GA4 Measurement Protocol API secret', 'rules' => ['nullable', 'string', 'max:255']],
            'ai_provider' => ['type' => 'select', 'label' => 'AI provider', 'options' => ['openai' => 'OpenAI', 'anthropic' => 'Anthropic'], 'rules' => ['nullable', 'string', 'in:openai,anthropic']],
            'openai_api_key' => ['type' => 'secret', 'label' => 'OpenAI API key', 'rules' => ['nullable', 'string', 'max:255']],
            'openai_model' => ['type' => 'text', 'label' => 'OpenAI model', 'rules' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9._:-]+$/']],
            'anthropic_api_key' => ['type' => 'secret', 'label' => 'Anthropic API key', 'rules' => ['nullable', 'string', 'max:255']],
            'anthropic_model' => ['type' => 'text', 'label' => 'Anthropic model', 'rules' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9._:-]+$/']],
            'ai_monthly_budget' => ['type' => 'number', 'label' => 'AI monthly budget (USD, 0 stops all AI calls)', 'rules' => ['nullable', 'numeric', 'min:0', 'max:1000000']],
        ],
        // Payment gateway credentials (M6.2). Each gateway in codefanz.payments.gateways lists its own keys.
        'payments' => [
            'stripe_mode' => ['type' => 'select', 'label' => 'Stripe mode', 'options' => ['test' => 'Test (sandbox)', 'live' => 'Live'], 'rules' => ['nullable', 'string', 'in:test,live']],
            'stripe_secret_key' => ['type' => 'secret', 'label' => 'Stripe secret key (sk_test_… / sk_live_…)', 'rules' => ['nullable', 'string', 'max:255', 'regex:/^(sk|rk)_(test|live)_[A-Za-z0-9]+$/']],
            'stripe_webhook_secret' => ['type' => 'secret', 'label' => 'Stripe webhook signing secret (whsec_…)', 'rules' => ['nullable', 'string', 'max:255', 'regex:/^whsec_[A-Za-z0-9]+$/']],
        ],
    ],

    /*
    | Integration status is derived from saved credentials only. A provider is
    | never shown as "Live" until a connection test exists for it.
    */

    'integrations' => [
        'google_analytics' => ['label' => 'Google Analytics 4', 'keys' => ['ga4_measurement_id', 'ga4_api_secret']],
        'openai' => ['label' => 'OpenAI (AI features)', 'keys' => ['openai_api_key'], 'ai_provider' => 'openai'],
        'anthropic' => ['label' => 'Anthropic (AI features)', 'keys' => ['anthropic_api_key'], 'ai_provider' => 'anthropic'],
    ],

    /*
    |--------------------------------------------------------------------------
    | AI provider layer
    |--------------------------------------------------------------------------
    |
    | The provider, models, keys and monthly budget live in Settings →
    | Integrations; these are the defaults and technical limits. Prices are
    | US dollars per million tokens and only feed the estimated cost in
    | ai_usage_logs (check them against the provider's price list). Unknown
    | models use the "default" row so the budget still applies.
    |
    */

    'ai' => [
        'default_provider' => env('AI_PROVIDER', 'openai'),
        'default_models' => [
            'openai' => env('OPENAI_MODEL', 'gpt-4.1-mini'),
            'anthropic' => env('ANTHROPIC_MODEL', 'claude-haiku-5-5'),
        ],
        'default_monthly_budget_usd' => (float) env('AI_MONTHLY_BUDGET_USD', 50),
        'timeout_seconds' => (int) env('AI_TIMEOUT_SECONDS', 30),
        'connect_timeout_seconds' => 5,
        // Retries only on HTTP 429 / 5xx and connection errors, with exponential backoff.
        'retries' => 2,
        'retry_base_ms' => 500,
        'openai' => [
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        ],
        'anthropic' => [
            // Same convention as the official SDKs: the host without /v1.
            'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),
            'version' => '2023-06-01',
            // Sent as output_config.effort when set; leave null for models that do not support it.
            'effort' => env('ANTHROPIC_EFFORT', 'low'),
        ],
        'prices' => [
            'gpt-4.1-mini' => ['input' => 0.40, 'output' => 1.60],
            'gpt-4.1' => ['input' => 2.00, 'output' => 8.00],
            'gpt-4o-mini' => ['input' => 0.15, 'output' => 0.60],
            'claude-opus-5-5' => ['input' => 4.00, 'output' => 20.00],
            'claude-sonnet-5-5' => ['input' => 2.00, 'output' => 10.00],
            'claude-haiku-5-5' => ['input' => 0.10, 'output' => 0.50],
            'default' => ['input' => 5.00, 'output' => 25.00],
        ],
    ],

    /*
    | AI Project Assistant (public, free). Limits apply per conversation and
    | per visitor IP; conversations are pruned daily (model:prune).
    */

    'assistant' => [
        'max_message_length' => 1000,
        'max_turns' => 30,
        'max_output_tokens' => 4000,
        'history_messages' => 40,
        'session_hours' => 24,
        'sessions_per_ip_per_hour' => 10,
        'messages_per_session_per_minute' => 8,
        'messages_per_ip_per_minute' => 20,
        'messages_per_ip_per_day' => 300,
        'retention_days' => (int) env('ASSISTANT_RETENTION_DAYS', 30),
        'lead_retention_days' => (int) env('ASSISTANT_LEAD_RETENTION_DAYS', 365),
    ],

    /*
    | Shown on error pages, which must render without the database (so it is
    | not read from Settings).
    */

    'support_email' => env('SUPPORT_EMAIL', 'hello@codefanz.com'),

    /*
    | Public contact form (anti-spam). Submissions faster than min_seconds or
    | older than max_minutes after the form was rendered are discarded.
    */

    'contact_form' => [
        'min_seconds' => 3,
        'max_minutes' => 120,
        'per_minute' => 3,
        'per_day' => 20,
    ],

    /*
    | Request a Quote attachments: stored on the private disk under random
    | names and only downloadable by staff with leads.view. Both the detected
    | MIME type and the file extension must match the allow-list.
    */

    'leads' => [
        'attachments' => [
            'max_files' => 5,
            'max_kb' => (int) env('LEAD_ATTACHMENT_MAX_KB', 10240),
            'extensions' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'png', 'jpg', 'jpeg', 'zip'],
            'disk' => env('MEDIA_PRIVATE_DISK', 'local'),
            'directory' => 'lead-attachments',
        ],
        'default_currency' => 'USD',
    ],

    'change_requests' => [
        'expires_after_days' => (int) env('CHANGE_REQUEST_EXPIRY_DAYS', 14),
    ],

    /*
    | Project delivery (M6): documents reuse the lead attachment allow-list
    | (detected MIME type and extension must both match) and the private disk.
    | Conversations are polled by the browser; support tickets marked resolved
    | close automatically after the given number of days (tickets:close-resolved).
    */

    'projects' => [
        'documents' => [
            'disk' => env('MEDIA_PRIVATE_DISK', 'local'),
            'directory' => 'project-documents',
        ],
        'message_max_length' => 5000,
        'poll_seconds' => 20,
        'default_currency' => 'USD',
    ],

    /*
    | Payments (M6.2). Gateways are resolved by key through
    | App\Services\Payments\PaymentGatewayManager: adding a provider means a new
    | driver class implementing App\Contracts\PaymentGateway plus an entry
    | here (and its credential fields in settings.payments). Credentials live
    | in Settings (encrypted); a gateway is offered on the pay page only when
    | its credentials are saved. Amounts are minor units, so only currencies
    | with two decimals are accepted for payment links.
    */

    'payments' => [
        'gateways' => [
            'stripe' => [
                'driver' => StripeGateway::class,
                'label' => 'Stripe',
                'method' => 'Credit or debit card',
                'description' => 'Visa, Mastercard · processed by Stripe',
                'credentials' => ['stripe_secret_key', 'stripe_webhook_secret'],
                'mode_key' => 'stripe_mode',
                'base_url' => env('STRIPE_BASE_URL', 'https://api.stripe.com'),
                // Pinned so response shapes do not change with the account's default API version.
                'api_version' => env('STRIPE_API_VERSION', '2024-06-20'),
                'currencies' => ['USD', 'EUR', 'GBP', 'EGP', 'SAR', 'AED'],
            ],
        ],
        'currencies' => ['USD', 'EUR', 'GBP', 'EGP', 'SAR', 'AED'],
        'webhook_tolerance_seconds' => 300,
        'timeout_seconds' => 20,
        'connect_timeout_seconds' => 5,
        'link_default_days' => 14,
        'invoice_due_days' => 14,
        'receipt_link_hours' => 72,
    ],

    'support' => [
        'auto_close_after_days' => (int) env('SUPPORT_AUTO_CLOSE_DAYS', 7),
        'reply_max_length' => 5000,
    ],

];
