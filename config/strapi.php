<?php

declare(strict_types=1);

/**
 * Konfiguracja Strapi CMS.
 */
return [
    /*
    |--------------------------------------------------------------------------
    | Strapi API URL
    |--------------------------------------------------------------------------
    |
    | URL do API Strapi CMS.
    |
    */

    'api_url' => env('STRAPI_API_URL', 'http://203.0.113.10:1339'),

    /*
    |--------------------------------------------------------------------------
    | Strapi API Token
    |--------------------------------------------------------------------------
    |
    | Token API do autoryzacji w Strapi (opcjonalnie).
    |
    */

    'api_token' => env('STRAPI_API_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Strapi Instances
    |--------------------------------------------------------------------------
    |
    | Konfiguracja różnych instancji Strapi.
    |
    */

    'instances' => [
        'sites' => [
            'api_url' => env('STRAPI_SITES_URL', 'http://203.0.113.10:1338'),
            'api_token' => env('STRAPI_SITES_TOKEN'),
        ],
        'motorent' => [
            'api_url' => env('STRAPI_MOTORENT_URL', 'http://203.0.113.10:1339'),
            'api_token' => env('STRAPI_MOTORENT_TOKEN'),
        ],
    ],
];
