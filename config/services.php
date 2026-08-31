<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
    'twilio' => [
        'sid' => env('TWILIO_SID'),
        'token' => env('TWILIO_AUTH_TOKEN'),
        'verify_sid' => env('TWILIO_VERIFY_SID'),
        'from' => env('TWILIO_PHONE_NUMBER'),
        'messaging_sid' => env('TWILIO_MESSAGING_SID'),
    ],

    'tap' => [
        'secret_key' => env('TAP_SECRET_KEY'),
        'public_key' => env('TAP_PUBLIC_KEY'),
        'merchant_id' => env('TAP_MERCHANT_ID'),
        'webhook_url' => env('TAP_WEBHOOK_URL', 'https://admin.azhlksa.com/api/v1/webhooks/tap'),
        'redirect_url' => env('TAP_REDIRECT_URL', 'https://admin.azhlksa.com/tap/redirect'),
    ],

    'authentica' => [
        'base_url' => env('AUTHENTICA_BASE_URL', 'https://api.authentica.sa/api/v2'),
        'api_key' => env('AUTHENTICA_API_KEY', '$2y$10$ypBhodlFFB3Rb.YhEBjJq.Jr0XcydJONFKYxBu.elHOzgRqgSunuG'),
        'app_hash' => env('AUTHENTICA_APP_HASH', 'Ii43T702uXm'),
        'template_id' => env('AUTHENTICA_TEMPLATE_ID'),
    ],

    'ibanapi' => [
        'base_url' => env('IBANAPI_BASE_URL', 'https://api.ibanapi.com/v1'),
        'key' => env('IBANAPI_KEY', ''),
    ],

];
