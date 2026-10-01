<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'health', 'health/*'],

    'allowed_origins' => [
        'http://localhost:3000',
        'http://127.0.0.1:3000',
        env('FRONTEND_URL', 'http://localhost:3000'),
    ],

    'allowed_origins_patterns' => [
        '#^https://.*\.vercel\.app$#',
        '#^https://.*\.loca\.lt$#',
        '#^https://.*\.ngrok-free\.app$#',
        '#^https://.*\.trycloudflare\.com$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
