<?php

declare(strict_types=1);

/**
 * Konfiguracja SSL/Certbot.
 */
return [
    /*
    |--------------------------------------------------------------------------
    | SSL Certificate Settings
    |--------------------------------------------------------------------------
    |
    | Ustawienia certyfikatów SSL przez Certbot.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Auto-renewal Threshold
    |--------------------------------------------------------------------------
    |
    | Automatyczne odnowienie certyfikatu jeśli wygasa w ciągu X dni.
    |
    */

    'renewal_threshold_days' => env('SSL_RENEWAL_THRESHOLD_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Certbot Email
    |--------------------------------------------------------------------------
    |
    | Email używany przy rejestracji certyfikatów Let's Encrypt.
    |
    */

    'certbot_email' => env('CERTBOT_EMAIL', config('mail.from.address')),

    /*
    |--------------------------------------------------------------------------
    | Fallback to Cloudflare SSL
    |--------------------------------------------------------------------------
    |
    | Czy używać Cloudflare SSL jako fallback (jeśli Certbot nie działa).
    |
    */

    'cloudflare_fallback' => env('SSL_CLOUDFLARE_FALLBACK', false),
];
