<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel'              => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // ── Midtrans Payment Gateway ──────────────────────────────────────────────
    'midtrans' => [
        'server_key'    => env('MIDTRANS_SERVER_KEY', ''),
        'client_key'    => env('MIDTRANS_CLIENT_KEY', ''),
        'is_production' => env('MIDTRANS_IS_PRODUCTION', false),
        'is_sanitized'  => true,
        'is_3ds'        => true,
    ],

    // ── Fonnte WhatsApp API ───────────────────────────────────────────────────
    'fonnte' => [
        'api_key' => env('FONNTE_API_KEY', ''),
    ],

    // ── Google reCAPTCHA ──────────────────────────────────────────────────────
    'recaptcha' => [
        'secret' => env('RECAPTCHA_SECRET_KEY', ''),
    ],

    // ── Google OAuth (Socialite) ──────────────────────────────────────────────
    'google' => [
        'client_id'     => env('GOOGLE_CLIENT_ID', ''),
        'client_secret' => env('GOOGLE_CLIENT_SECRET', ''),
        'redirect'      => env('GOOGLE_REDIRECT_URI', 'http://localhost:8000/api/auth/google/callback'),
    ],

];