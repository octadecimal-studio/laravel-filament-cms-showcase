<?php

declare(strict_types=1);

/**
 * Konfiguracja OVH API.
 *
 * Credentials są wczytywane z .admin (przez AppServiceProvider)
 * lub z .env jako fallback.
 */
return [
    /*
    |--------------------------------------------------------------------------
    | OVH API Credentials
    |--------------------------------------------------------------------------
    |
    | Klucze API OVH są przechowywane w pliku .admin w katalogu głównym projektu.
    | W środowisku produkcyjnym powinny być w .env.
    |
    */

    'app_key' => env('OVH_APP_KEY'),
    'app_secret' => env('OVH_APP_SECRET'),
    'customer_key' => env('OVH_CUSTOMER_KEY'),

    /*
    |--------------------------------------------------------------------------
    | OVH API Endpoint
    |--------------------------------------------------------------------------
    |
    | Endpoint OVH API (EU).
    |
    */

    'endpoint' => env('OVH_ENDPOINT', 'https://eu.api.ovh.com/1.0'),

    /*
    |--------------------------------------------------------------------------
    | Cache DNS Records
    |--------------------------------------------------------------------------
    |
    | Czas cache dla rekordów DNS (w sekundach).
    | Domyślnie 5 minut (300 sekund).
    |
    */

    'cache_ttl' => env('OVH_CACHE_TTL', 300),
];
