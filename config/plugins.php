<?php

/**
 * Konfiguracja systemu pluginów.
 *
 * Pluginy są automatycznie wykrywane w app/Plugins/,
 * ale można też ręcznie dodać do listy 'plugins'.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Lista pluginów (opcjonalna)
    |--------------------------------------------------------------------------
    |
    | Pluginy są auto-discovered w app/Plugins/, ale możesz też
    | ręcznie dodać klasy pluginów tutaj.
    |
    */
    'plugins' => [
        // App\Plugins\Reservations\ReservationsPlugin::class,
        // App\Plugins\Shop\ShopPlugin::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Włączanie/wyłączanie pluginów
    |--------------------------------------------------------------------------
    |
    | Globalnie włącz/wyłącz konkretne pluginy.
    | Wyłączony plugin nie może być instalowany na żadnej stronie.
    |
    */
    'enabled' => [
        'reservations' => env('PLUGIN_RESERVATIONS_ENABLED', true),
        'shop' => env('PLUGIN_SHOP_ENABLED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Ładowanie routes w konsoli
    |--------------------------------------------------------------------------
    |
    | Czy ładować routes pluginów gdy aplikacja działa w konsoli.
    | Przydatne dla testów, normalnie false.
    |
    */
    'load_routes_in_console' => env('PLUGIN_LOAD_ROUTES_CONSOLE', false),

    /*
    |--------------------------------------------------------------------------
    | Prefiks tabel pluginów
    |--------------------------------------------------------------------------
    |
    | Wszystkie tabele pluginów używają tego prefiksu.
    | Format: {prefix}_{plugin_slug}_{table_name}
    | np. plugin_reservations_reservations
    |
    */
    'table_prefix' => 'plugin_',

    /*
    |--------------------------------------------------------------------------
    | Cache TTL
    |--------------------------------------------------------------------------
    |
    | Czas cache dla danych pluginów (w sekundach).
    |
    */
    'cache_ttl' => env('PLUGIN_CACHE_TTL', 3600),

];
