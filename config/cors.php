<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    | Izinkan frontend React (port 3000) mengakses Laravel API (port 8000).
    | Sesuaikan allowed_origins jika deploy ke domain berbeda.
    */
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'storage/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'https://rezi.yopaaa.xyz',
        'http://localhost:3000',
        env('FRONTEND_URL', ''),
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];