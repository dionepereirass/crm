<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services & Messaging Gateways
    |--------------------------------------------------------------------------
    */

    'providers' => [
        'mock_enabled' => (bool) env('MOCK_PROVIDERS', true),
    ],

    'zenvia' => [
        'enabled' => (bool) env('ZENVIA_ENABLED', false),
        'api_key' => env('ZENVIA_API_KEY'),
        'from' => env('ZENVIA_FROM'),
    ],

    'brevo' => [
        'enabled' => (bool) env('BREVO_ENABLED', false),
        'api_key' => env('BREVO_API_KEY'),
        'sender_email' => env('BREVO_SENDER_EMAIL'),
        'sender_name' => env('BREVO_SENDER_NAME', 'BET CRM'),
    ],

    'infobip' => [
        'enabled' => (bool) env('INFOBIP_ENABLED', false),
        'api_key' => env('INFOBIP_API_KEY'),
        'base_url' => env('INFOBIP_BASE_URL'),
    ],

    'sendgrid' => [
        'enabled' => (bool) env('SENDGRID_ENABLED', false),
        'api_key' => env('SENDGRID_API_KEY'),
        'from_email' => env('SENDGRID_FROM_EMAIL'),
        'from_name' => env('SENDGRID_FROM_NAME', 'BET CRM'),
    ],

];
