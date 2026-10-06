<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
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

    // Login sosial (lihat App\Support\Oauth). Tombol hanya muncul kalau id & secret diisi.
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    ],

    'line' => [
        'client_id' => env('LINE_CHANNEL_ID'),
        'client_secret' => env('LINE_CHANNEL_SECRET'),
    ],

    // Pembayaran. Gateway hanya aktif kalau kuncinya diisi (lihat App\Payments).
    'paypal' => [
        'client_id' => env('PAYPAL_CLIENT_ID'),
        'client_secret' => env('PAYPAL_CLIENT_SECRET'),
        'webhook_id' => env('PAYPAL_WEBHOOK_ID'),
        'mode' => env('PAYPAL_MODE', 'sandbox'), // sandbox / live
    ],

    // Notifikasi push aplikasi admin. Isi FIREBASE_CREDENTIALS dengan file JSON service account
    // (Firebase console -> Project settings -> Service accounts) yang di-base64 jadi satu baris.
    'fcm' => [
        'credentials' => env('FIREBASE_CREDENTIALS'),
    ],

    'xendit' => [
        'secret_key' => env('XENDIT_SECRET_KEY'),
        'callback_token' => env('XENDIT_CALLBACK_TOKEN'),
        'va_banks' => ['BCA', 'BNI', 'BRI', 'MANDIRI', 'PERMATA'],
    ],

];
